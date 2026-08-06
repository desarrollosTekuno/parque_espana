<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\CollectionNote;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Act;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\Membership;
use App\Models\Members\MemberDocument;
use App\Models\Memberships\MembershipAccount;
use App\Models\AdminClub\CafeteriaVisit;
use App\Rules\ExistsInSchema;
use App\Services\AdminClub\CafeteriaCheckoutService;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\PaymentRegistrationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

/**
 * Módulo de cobranza tipo caja (nuevo).
 *
 * Flujo: el encargado busca al socio por clave o nombre, revisa sus cargos
 * pendientes agrupados por concepto, arma una lista de cobros (cargos
 * existentes + conceptos nuevos capturados a mano) y efectúa el pago.
 *
 * Reutiliza los modelos y el PaymentRegistrationService existentes sin
 * modificar el módulo de facturación previo.
 */
class CollectionController extends Controller
{
    /** Meses en español para las etiquetas de periodo. */
    private const MONTHS = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function __construct(
        protected PaymentRegistrationService $paymentRegistrationService,
        protected MembershipChargeService $membershipChargeService,
        protected CafeteriaCheckoutService $cafeteriaCheckoutService,
    ) {
    }

    /**
     * Pantalla principal del módulo. Los datos del socio se cargan de forma
     * asíncrona vía search(); aquí solo mandamos catálogos.
     */
    public function index()
    {
        $conceptOptions = ChargeConcept::query()
            ->select('id', 'code', 'name', 'default_amount', 'is_recurring', 'allows_partial_payments')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return Inertia::render('Collections/Index', [
            'conceptOptions' => $conceptOptions,
            'clubPaymentMethods' => $this->resolveClubPaymentMethods(),
        ]);
    }

    /**
     * Busca un socio por clave (no. cuenta / cuenta interna) o nombre del
     * titular y devuelve todo el contexto de cobranza en JSON.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $search = trim($validated['query']);
        $driver = DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ilike' : 'like';
        $sessionClubId = (int) session('club_id');

        // Un socio con membresía en más de un parque tiene una MembershipAccount
        // distinta por parque (ver resolveGroupAccountIds). Cuando la búsqueda
        // por nombre empata con varias de esas cuentas, se prioriza la del
        // parque de la sesión: de lo contrario se elegía la de mayor id sin
        // importar en qué parque está parado el encargado, lo que forzaba el
        // cobro (y por tanto el "Agregar" de cargos de un solo parque, como
        // GUEST_LIST) al parque equivocado.
        $account = MembershipAccount::query()
            ->with(['primaryHolder.member'])
            ->whereHas('primaryHolder.member')
            ->where(function (Builder $query) use ($search, $like, $driver) {
                $query->where('membership_number', $like, "%{$search}%")
                    ->orWhere('internal_account_number', $like, "%{$search}%")
                    ->orWhereHas('primaryHolder.member', function (Builder $memberQuery) use ($search, $like, $driver) {
                        $memberQuery->where('first_name', $like, "%{$search}%")
                            ->orWhere('last_name', $like, "%{$search}%")
                            ->orWhere('second_last_name', $like, "%{$search}%")
                            ->orWhereRaw(
                                $driver === 'pgsql'
                                    ? "CONCAT(first_name, ' ', last_name, ' ', COALESCE(second_last_name, '')) ILIKE ?"
                                    : "CONCAT(first_name, ' ', last_name, ' ', IFNULL(second_last_name, '')) LIKE ?",
                                ["%{$search}%"]
                            );
                    });
            })
            ->when(
                $sessionClubId,
                fn (Builder $q) => $q->orderByRaw('CASE WHEN club_id = ? THEN 0 ELSE 1 END', [$sessionClubId])
            )
            ->orderByDesc('id')
            ->first();

        if (!$account) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontró ningún socio con esa clave o nombre.',
            ]);
        }

        // Membresía sobre la que se factura: primaria activa/suspendida,
        // priorizando el parque de la sesión.
        $membership = Membership::query()
           // ->with('club')
            ->where('membership_account_id', $account->id)
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->orderByRaw('CASE WHEN club_id = ? THEN 0 ELSE 1 END', [$sessionClubId])
            ->first();

        $cobroClub = $membership?->club
            ?? ($sessionClubId ? Club::find($sessionClubId) : null);
        $cobroClubId = $cobroClub?->id;

        // Un socio que pertenece a más de un parque tiene una MembershipAccount
        // distinta por parque, enlazadas por account_group_id (mismo mecanismo
        // que usa MemberController/MembershipChargeService para repartir la
        // cuota interclub). Se buscan aquí para poder agregar la mensualidad
        // de todos los parques del socio en un solo concepto.
        $groupAccountIds = $this->resolveGroupAccountIds($account);

        // Todas las membresías primarias activas/suspendidas del socio en
        // cualquiera de sus cuentas del grupo. Se usan para agregar la
        // mensualidad de todos los parques en un solo concepto y para generar
        // el cargo del mes siguiente en cada una si aún no existe.
        $accountMemberships = Membership::query()
            ->with(['club', 'account'])
            ->whereIn('membership_account_id', $groupAccountIds)
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->get();

        foreach ($accountMemberships as $accountMembership) {
            $this->membershipChargeService->ensureMonthlyChargeForNextPeriod($accountMembership);
        }

        // Un parque por cada membresía activa del socio (puede pertenecer a
        // más de una MembershipAccount, una por parque, ver resolveGroupAccountIds),
        // para mostrar en el encabezado la clave/cuenta que le corresponde en
        // cada uno cuando aplica.
        $clubMemberships = $accountMemberships
            ->unique('club_id')
            ->map(fn (Membership $m) => [
                'club_id' => $m->club_id,
                'club_code' => $m->club?->code,
                'club_name' => $m->club?->name,
                'membership_number' => $m->account?->membership_number,
                'is_cobro_club' => $m->club_id === $cobroClubId,
            ])
            ->values();

        $holder = $account->primaryHolder?->member;
        $holderName = trim(collect([
            $holder?->first_name,
            $holder?->last_name,
            $holder?->second_last_name,
        ])->filter()->implode(' '));

        // ── Integrantes de la cuenta, para la asignación de casilleros ──
        // (ver "Agregar concepto de cobro" → código LOCKERS en el frontend).
        $pendingLockerMemberIds = Charge::where('status', 'pending')
            ->where('period_year', now()->year)
            ->whereNotNull('metadata->locker_id')
            ->pluck('member_id')
            ->unique();

        $accountMembers = $account->members()
            ->get()
            ->load('lockerAssignment')
            ->map(function ($member) use ($pendingLockerMemberIds) {
                $hasLocker = $member->lockerAssignment !== null;
                $hasPendingLocker = $pendingLockerMemberIds->contains($member->id);

                return [
                    'id' => $member->id,
                    'name' => trim(collect([
                        $member->first_name,
                        $member->last_name,
                        $member->second_last_name,
                    ])->filter()->implode(' ')),
                    'has_locker' => $hasLocker || $hasPendingLocker,
                ];
            })
            ->values();

        // ── Cargos pendientes, agrupados por concepto ──
        // Se muestran los de todas las cuentas del grupo (todos los parques
        // donde el socio tiene membresía), no solo los de la cuenta encontrada
        // por la búsqueda.
        $pendingCharges = Charge::query()
            ->with(['concept', 'membership.club'])
            ->whereIn('status', ['pending', 'partial'])
            ->whereIn('membership_account_id', $groupAccountIds)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        // El reparto entre parques solo aplica a la mensualidad (MONTHLY_FEE):
        // por eso solo esa se agrupa por concepto a través de todas las
        // cuentas del grupo. Cualquier otro cargo (inscripción, casilleros,
        // pase diario, cafetería, etc.) se agrupa por concepto + parque, para
        // que cada uno quede en su propio renglón, en el parque que le
        // corresponde, sin mezclarse ni marcarse como dividido.
        //
        // La mensualidad además se agrupa por periodo (año-mes): antes se
        // juntaban todos los meses pendientes en un solo renglón; ahora cada
        // mes queda separado (aunque tenga 2 cargos si se reparte entre
        // parques) para poder mostrar de qué mes es y bloquear los meses
        // posteriores hasta que los anteriores se agreguen a la lista de
        // cobros (ver isMonthlyPeriodLocked en el frontend).
        $pendingConcepts = $pendingCharges
            ->groupBy(function (Charge $charge) {
                if ($charge->concept?->code === 'MONTHLY_FEE') {
                    return sprintf('%d-%04d-%02d', $charge->concept_id, $charge->period_year ?? 0, $charge->period_month ?? 0);
                }

                return $charge->concept_id . '-' . ($charge->membership?->club_id ?? 'none');
            })
            ->map(function ($group) {
                /** @var \App\Models\Billing\ChargeConcept|null $concept */
                $concept = $group->first()->concept;
                $isMonthlyFee = $concept?->code === 'MONTHLY_FEE';
                // Cada renglón de mensualidad representa exactamente un mes
                // (aunque tenga un cargo por parque), así que no hay nada que
                // promediar entre "meses" dentro del grupo.
                $months = $isMonthlyFee ? 1 : $group->count();
                $balance = round((float) $group->sum('balance'), 2);
                $originalTotal = round((float) $group->sum('amount'), 2);
                $isRecurring = (bool) ($concept?->is_recurring);

                $clubIds = $group->map(fn (Charge $c) => $c->membership?->club_id)->filter()->unique();
                $isMultiClub = $clubIds->count() > 1;
                $clubBreakdown = $isMultiClub
                    ? $group->groupBy(fn (Charge $c) => $c->membership?->club_id)
                        ->map(function ($clubGroup) {
                            $club = $clubGroup->first()->membership?->club;

                            return [
                                'club_id' => $club?->id,
                                'club_code' => $club?->code,
                                'club_name' => $club?->name,
                                'amount' => round((float) $clubGroup->sum('balance'), 2),
                            ];
                        })
                        ->values()
                    : collect();

                $firstCharge = $group->first();

                return [
                    'concept_id' => $concept?->id,
                    'concept_code' => $concept?->code,
                    'concept_name' => $concept?->name,
                    // "tasa" queda vacía por ahora (a definir después).
                    'rate' => null,
                    // Cuota original por mes/unidad.
                    'fee' => $months > 0 ? round($originalTotal / $months, 2) : $originalTotal,
                    'class_label' => $isRecurring ? 'A meses' : 'Una exhibición',
                    // Monto = adeudo dividido entre los meses que aplican.
                    'unit_amount' => $months > 0 ? round($balance / $months, 2) : $balance,
                    'months' => $months,
                    'balance' => $balance,
                    'is_multi_club' => $isMultiClub,
                    'club_breakdown' => $clubBreakdown,
                    'period_year' => $isMonthlyFee ? $firstCharge->period_year : null,
                    'period_month' => $isMonthlyFee ? $firstCharge->period_month : null,
                    'period_label' => $isMonthlyFee
                        ? $this->periodLabel($firstCharge->period_month, $firstCharge->period_year)
                        : null,
                    'charges' => $group->map(fn (Charge $charge) => [
                        'id' => $charge->id,
                        'balance' => round((float) $charge->balance, 2),
                        'allows_partial_payments' => (bool) $charge->allows_partial_payments,
                        'period_label' => $this->periodLabel($charge->period_month, $charge->period_year),
                        'club_id' => $charge->membership?->club_id,
                        'club_code' => $charge->membership?->club?->code,
                    ])->values(),
                ];
            })
            ->values();

        // Los renglones de mensualidad quedan en su posición original, pero
        // ordenados cronológicamente entre sí (mes más antiguo primero): el
        // frontend depende de este orden para saber cuál es "el siguiente
        // mes a pagar" y bloquear los posteriores.
        $monthlySlots = $pendingConcepts->keys()
            ->filter(fn ($index) => $pendingConcepts[$index]['concept_code'] === 'MONTHLY_FEE')
            ->values();

        if ($monthlySlots->count() > 1) {
            $sortedMonthly = $monthlySlots
                ->map(fn ($index) => $pendingConcepts[$index])
                ->sortBy(fn ($row) => $row['period_year'] * 100 + $row['period_month'])
                ->values();

            foreach ($monthlySlots as $slotPosition => $originalIndex) {
                $pendingConcepts[$originalIndex] = $sortedMonthly[$slotPosition];
            }
        }

        // ── Resumen ──
        $lastPaid = Charge::query()
            ->whereHas('concept', fn (Builder $q) => $q->where('code', 'MONTHLY_FEE'))
            ->where('membership_account_id', $account->id)
            ->when(
                $cobroClubId,
                fn (Builder $q) => $q->whereHas('membership', fn (Builder $m) => $m->where('club_id', $cobroClubId))
            )
            ->where('status', 'paid')
            ->whereNotNull('period_year')
            ->whereNotNull('period_month')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        $overdueMonths = Charge::query()
            ->whereHas('concept', fn (Builder $q) => $q->where('code', 'MONTHLY_FEE'))
            ->where('membership_account_id', $account->id)
            ->when(
                $cobroClubId,
                fn (Builder $q) => $q->whereHas('membership', fn (Builder $m) => $m->where('club_id', $cobroClubId))
            )
            ->whereIn('status', ['pending', 'partial'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->count();

        $memberIds = $account->accountMembers()->pluck('member_id');
        $lockersCount = $memberIds->isNotEmpty()
            ? LockerAssignment::whereIn('member_id', $memberIds)
                ->where('year', now()->year)
                ->count()
            : 0;

        $totalDue = round((float) $pendingCharges->sum('balance'), 2);

        // ── Incidencias (actas) ──
        $incidents = Act::query()
            ->where('account_id', $account->id)
            ->orderByDesc('date')
            ->limit(20)
            ->get()
            ->map(fn (Act $act) => [
                'id' => $act->id,
                'folio' => $act->folio,
                'violation_type' => $act->violation_type,
                'description' => $act->description,
                'date' => optional($act->date)->format('Y-m-d') ?? (string) $act->date,
            ]);

        // ── Notas de cobranza capturadas en este módulo ──
        $notes = CollectionNote::query()
            ->with('author')
            ->where('membership_account_id', $account->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (CollectionNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author?->name,
                'created_at' => optional($note->created_at)->format('Y-m-d H:i'),
            ]);

        // ── Señales automáticas de comportamiento de pago ──
        $signals = [];
        if ($overdueMonths >= 3) {
            $signals[] = ['label' => "Moroso recurrente ({$overdueMonths} meses)", 'color' => 'error'];
        } elseif ($overdueMonths >= 1) {
            $signals[] = ['label' => "Con adeudo vencido ({$overdueMonths} meses)", 'color' => 'warning'];
        }
        if ($incidents->isNotEmpty()) {
            $signals[] = ['label' => 'Con incidencias registradas', 'color' => 'warning'];
        }
        if ($totalDue <= 0) {
            $signals[] = ['label' => 'Sin adeudo', 'color' => 'success'];
        }

        return response()->json([
            'found' => true,
            'account' => [
                'id' => $account->id,
                'membership_number' => $account->membership_number,
                'internal_account_number' => $account->internal_account_number,
                'holder_name' => $holderName ?: '—',
                'holder_member_id' => $holder?->id,
                'email' => $holder?->email,
                'phone' => $holder?->phone,
                'photo' => $this->resolveHolderPhotoUrl($holder),
            ],
            'cobro_club' => $cobroClub ? [
                'id' => $cobroClub->id,
                'code' => $cobroClub->code,
                'name' => $cobroClub->name,
            ] : null,
            'club_memberships' => $clubMemberships,
            'account_members' => $accountMembers,
            'billing_membership_id' => $membership?->id,
            'pending_concepts' => $pendingConcepts,
            'summary' => [
                'last_paid_period_label' => $lastPaid
                    ? $this->periodLabel($lastPaid->period_month, $lastPaid->period_year)
                    : null,
                'overdue_months' => $overdueMonths,
                'lockers_count' => $lockersCount,
                'total_due' => $totalDue,
            ],
            'incidents' => $incidents,
            'notes' => $notes,
            'signals' => $signals,
        ]);
    }

    /**
     * Efectúa el cobro: genera los cargos de los conceptos nuevos capturados
     * y aplica el pago (cargos existentes + nuevos) reutilizando el servicio
     * de registro de pagos. Es una operación atómica.
     */
    public function storePayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'membership_account_id' => ['required', new ExistsInSchema('memberships', 'accounts', 'id')],
                'club_id' => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
                'paid_at' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],

                // Formas de pago: puede ser más de una (p. ej. una parte en
                // efectivo y otra con tarjeta); la suma debe cubrir el total
                // exacto de lo seleccionado (ver PaymentRegistrationService::registerSplit).
                'payments' => ['required', 'array', 'min:1'],
                'payments.*.payment_method_id' => ['required', new ExistsInSchema('billing', 'payment_methods', 'id')],
                'payments.*.amount' => ['required', 'numeric', 'gt:0'],
                'payments.*.reference' => ['nullable', 'string', 'max:255'],
                'payments.*.bank_name' => ['nullable', 'string', 'max:255'],
                'payments.*.check_number' => ['nullable', 'string', 'max:255'],

                // Cargos existentes seleccionados
                'existing_charges' => ['array'],
                'existing_charges.*.charge_id' => ['required', new ExistsInSchema('billing', 'charges', 'id')],
                'existing_charges.*.amount' => ['required', 'numeric', 'gt:0'],

                // Conceptos nuevos capturados a mano
                'new_items' => ['array'],
                'new_items.*.concept_id' => ['required', new ExistsInSchema('billing', 'concepts', 'id')],
                'new_items.*.description' => ['nullable', 'string', 'max:255'],
                'new_items.*.total' => ['required', 'numeric', 'gt:0'],

                // Salidas de cafetería capturadas en "Agregar concepto de
                // cobro": la visita solo se da por cerrada (y el cargo, si
                // aplica, solo se genera) hasta que se confirme este pago.
                'cafeteria_checkouts' => ['array'],
                'cafeteria_checkouts.*.visit_id' => ['required', new ExistsInSchema('guest_lists', 'cafeteria_visits', 'id')],
                'cafeteria_checkouts.*.consumption_amount' => ['required', 'numeric', 'min:0'],
            ]);

            $existing = collect($validated['existing_charges'] ?? []);
            $newItems = collect($validated['new_items'] ?? []);
            $cafeteriaCheckouts = collect($validated['cafeteria_checkouts'] ?? []);

            if ($existing->isEmpty() && $newItems->isEmpty() && $cafeteriaCheckouts->isEmpty()) {
                throw ValidationException::withMessages([
                    'applications' => 'Agrega al menos un cargo o concepto a la lista de cobros.',
                ]);
            }

            $account = MembershipAccount::query()
                ->with('primaryHolder.member')
                ->findOrFail($validated['membership_account_id']);
            $clubId = (int) $validated['club_id'];

            $paymentMethodIds = collect($validated['payments'])->pluck('payment_method_id')->unique()->values();
            $activePaymentMethodCount = PaymentMethod::whereIn('id', $paymentMethodIds)->where('is_active', true)->count();

            if ($activePaymentMethodCount !== $paymentMethodIds->count()) {
                throw ValidationException::withMessages([
                    'payments' => 'Una o más formas de pago seleccionadas no están activas.',
                ]);
            }

            // Todas las cuentas del grupo del socio (una por parque, ver
            // resolveGroupAccountIds) y los parques donde tiene membresía
            // activa en cualquiera de ellas: permite que la mensualidad se
            // liquide en un solo pago aunque abarque más de un club (ver
            // PaymentRegistrationService::ensureChargesBelongToClub).
            $groupAccountIds = $this->resolveGroupAccountIds($account);

            // No se puede saldar una mensualidad si queda una anterior
            // pendiente (de cualquier parque del socio) que no se está
            // incluyendo en este mismo pago: p. ej. no se puede pagar mayo
            // sin haber cubierto abril. El frontend ya bloquea esto en la
            // interfaz; esta es la validación real en el servidor.
            $this->ensureMonthlyChargesArePaidInOrder(
                $existing->pluck('charge_id')->map(fn ($id) => (int) $id),
                $groupAccountIds
            );

            $accountClubIds = Membership::query()
                ->whereIn('membership_account_id', $groupAccountIds)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->pluck('club_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Membresía del socio en ese parque para colgar los cargos nuevos.
            $membership = Membership::query()
                ->where('membership_account_id', $account->id)
                ->where('club_id', $clubId)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->first();

            if ($newItems->isNotEmpty() && !$membership) {
                throw ValidationException::withMessages([
                    'new_items' => 'El socio no tiene una membresía activa en este parque para generar cargos nuevos.',
                ]);
            }

            $memberId = $account->primaryHolder?->member_id;

            $payments = DB::transaction(function () use (
                $account, $clubId, $existing, $newItems, $cafeteriaCheckouts,
                $membership, $memberId, $validated, $request, $accountClubIds,
                $groupAccountIds
            ) {
                $applications = $existing
                    ->map(fn ($item) => [
                        'charge_id' => (int) $item['charge_id'],
                        'amount' => round((float) $item['amount'], 2),
                    ])
                    ->values()
                    ->all();

                // Cierra cada visita de cafetería seleccionada (marca la
                // salida) y, si el consumo no alcanzó el mínimo, genera su
                // cargo pendiente — todo dentro de esta misma transacción,
                // para que no quede nada registrado si el pago no se
                // confirma.
                foreach ($cafeteriaCheckouts as $checkout) {
                    $visit = CafeteriaVisit::lockForUpdate()->findOrFail($checkout['visit_id']);

                    $charge = $this->cafeteriaCheckoutService->checkout(
                        cafeteriaVisit: $visit,
                        consumption: round((float) $checkout['consumption_amount'], 2),
                        checkedOutBy: $request->user()?->id,
                        chargePending: true,
                    );

                    if ($charge) {
                        $applications[] = [
                            'charge_id' => $charge->id,
                            'amount' => (float) $charge->amount,
                        ];
                    }
                }

                // Genera un cargo pendiente por cada concepto nuevo y lo agrega
                // a la lista de aplicaciones a su monto total.
                foreach ($newItems as $item) {
                    $total = round((float) $item['total'], 2);
                    $concept = ChargeConcept::find($item['concept_id']);

                    $charge = Charge::create([
                        'membership_account_id' => $account->id,
                        'membership_id' => $membership?->id,
                        'member_id' => $memberId,
                        'concept_id' => $item['concept_id'],
                        'description' => $item['description']
                            ?? $concept?->name
                            ?? 'Cargo de cobranza',
                        'amount' => $total,
                        'balance' => $total,
                        'issue_date' => now()->toDateString(),
                        'due_date' => now()->toDateString(),
                        'allows_partial_payments' => false,
                        'status' => 'pending',
                        'metadata' => [
                            'charge_origin' => 'collections_desk',
                            'created_by' => $request->user()?->id,
                        ],
                    ]);

                    $applications[] = [
                        'charge_id' => $charge->id,
                        'amount' => $total,
                    ];
                }

                return $this->paymentRegistrationService->registerSplit(
                    account: $account,
                    clubId: $clubId,
                    applications: $applications,
                    payments: $validated['payments'],
                    paidAt: $validated['paid_at'],
                    notes: $validated['notes'] ?? null,
                    receivedBy: $request->user()?->id,
                    sessionClubId: session('club_id'),
                    accountClubIds: $accountClubIds,
                    groupAccountIds: $groupAccountIds,
                );
            });

            $totalPaid = round($payments->sum('amount'), 2);

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Cobro registrado correctamente por $%s.',
                    number_format($totalPaid, 2)
                ),
                'payment_ids' => $payments->pluck('id')->values(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al registrar el cobro.',
                'exception' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * IDs de todas las MembershipAccount del mismo grupo que la cuenta dada
     * (mismo account_group_id), o solo la propia si no pertenece a ningún
     * grupo. Un socio que pertenece a más de un parque tiene una cuenta
     * distinta por parque, enlazadas por este mismo mecanismo que ya usa
     * MemberController/MembershipChargeService para repartir la cuota
     * interclub.
     */
    protected function resolveGroupAccountIds(MembershipAccount $account): array
    {
        if (!$account->account_group_id) {
            return [$account->id];
        }

        return MembershipAccount::query()
            ->where('account_group_id', $account->account_group_id)
            ->pluck('id')
            ->all();
    }

    /**
     * Lanza una ValidationException si el pago intenta saldar una mensualidad
     * sin cubrir también las mensualidades pendientes de meses anteriores
     * (considerando todas las cuentas del grupo del socio, es decir todos sus
     * parques): la mensualidad debe pagarse en orden, mes por mes.
     */
    protected function ensureMonthlyChargesArePaidInOrder(\Illuminate\Support\Collection $existingChargeIds, array $groupAccountIds): void
    {
        $chargesBeingPaid = Charge::query()
            ->whereIn('id', $existingChargeIds)
            ->whereHas('concept', fn (Builder $q) => $q->where('code', 'MONTHLY_FEE'))
            ->get(['id', 'period_year', 'period_month']);

        if ($chargesBeingPaid->isEmpty()) {
            return;
        }

        $latestPeriodBeingPaid = $chargesBeingPaid
            ->max(fn (Charge $c) => ($c->period_year ?? 0) * 100 + ($c->period_month ?? 0));

        $missingEarlier = Charge::query()
            ->whereIn('membership_account_id', $groupAccountIds)
            ->whereHas('concept', fn (Builder $q) => $q->where('code', 'MONTHLY_FEE'))
            ->whereIn('status', ['pending', 'partial'])
            ->whereNotIn('id', $existingChargeIds)
            ->get(['id', 'period_year', 'period_month'])
            ->filter(fn (Charge $c) => (($c->period_year ?? 0) * 100 + ($c->period_month ?? 0)) <= $latestPeriodBeingPaid);

        if ($missingEarlier->isNotEmpty()) {
            throw ValidationException::withMessages([
                'existing_charges' => 'Hay mensualidades de meses anteriores pendientes. Agrégalas también antes de pagar un mes posterior.',
            ]);
        }
    }

    protected function resolveHolderPhotoUrl(?\App\Models\Members\Member $holder): ?string
    {
        $photoDocument = $holder?->documents
            ->first(fn (MemberDocument $document) => $document->documentType?->code === 'fotografia_infantil');

        if (!$photoDocument) {
            return null;
        }

        return Storage::disk('spaces')->temporaryUrl(
            $photoDocument->file_path,
            now()->addMinutes(30)
        );
    }

    /**
     * Guarda una nota/comentario de cobranza para el socio.
     */
    public function storeNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_account_id' => ['required', new ExistsInSchema('memberships', 'accounts', 'id')],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $note = CollectionNote::create([
            'membership_account_id' => $validated['membership_account_id'],
            'club_id' => session('club_id') ?: null,
            'created_by' => $request->user()?->id,
            'body' => $validated['body'],
        ]);

        $note->load('author');

        return response()->json([
            'success' => true,
            'note' => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author?->name,
                'created_at' => optional($note->created_at)->format('Y-m-d H:i'),
            ],
        ]);
    }

    private function periodLabel(?int $month, ?int $year): ?string
    {
        if (!$month || !$year) {
            return null;
        }

        return (self::MONTHS[$month] ?? (string) $month) . ' ' . $year;
    }

    /**
     * Métodos de pago habilitados por parque (mismo criterio que el módulo
     * de facturación: respeta el permiso de métodos que no afectan corte).
     */
    private function resolveClubPaymentMethods(): \Illuminate\Support\Collection
    {
        $canUseNonCashCut = auth()->user()?->can('billing.payments.non-cash-cut') ?? false;

        return Club::query()
            ->with([
                'clubPaymentMethods' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('display_order'),
                'clubPaymentMethods.paymentMethod',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Club $club) use ($canUseNonCashCut) {
                return [
                    'id' => $club->id,
                    'code' => $club->code,
                    'name' => $club->name,
                    'payment_methods' => $club->clubPaymentMethods
                        ->map(fn ($clubPaymentMethod) => [
                            'id' => $clubPaymentMethod->paymentMethod?->id,
                            'code' => $clubPaymentMethod->paymentMethod?->code,
                            'name' => $clubPaymentMethod->paymentMethod?->name,
                            'requires_reference' => (bool) $clubPaymentMethod->paymentMethod?->requires_reference,
                            'requires_bank_name' => (bool) $clubPaymentMethod->paymentMethod?->requires_bank_name,
                            'requires_check_number' => (bool) $clubPaymentMethod->paymentMethod?->requires_check_number,
                            'affects_cash_cut' => (bool) $clubPaymentMethod->paymentMethod?->affects_cash_cut,
                            'show_in_billing' => (bool) $clubPaymentMethod->paymentMethod?->show_in_billing,
                            'internal_key' => $clubPaymentMethod->internal_key,
                        ])
                        ->filter(function (array $method) use ($canUseNonCashCut) {
                            if (empty($method['id'])) return false;
                            if (!$method['show_in_billing']) return false;
                            if (!$method['affects_cash_cut'] && !$canUseNonCashCut) return false;
                            return true;
                        })
                        ->values(),
                ];
            })
            ->values();
    }
}
