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
use App\Models\Memberships\MembershipAccount;
use App\Rules\ExistsInSchema;
use App\Services\Billing\PaymentRegistrationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

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
            ->orderByDesc('id')
            ->first();

        if (!$account) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontró ningún socio con esa clave o nombre.',
            ]);
        }

        $sessionClubId = (int) session('club_id');

        // Membresía sobre la que se factura: primaria activa/suspendida,
        // priorizando el parque de la sesión.
        $membership = Membership::query()
            ->with('club')
            ->where('membership_account_id', $account->id)
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->orderByRaw('CASE WHEN club_id = ? THEN 0 ELSE 1 END', [$sessionClubId])
            ->first();

        $cobroClub = $membership?->club
            ?? ($sessionClubId ? Club::find($sessionClubId) : null);
        $cobroClubId = $cobroClub?->id;

        $holder = $account->primaryHolder?->member;
        $holderName = trim(collect([
            $holder?->first_name,
            $holder?->last_name,
            $holder?->second_last_name,
        ])->filter()->implode(' '));

        // ── Cargos pendientes del parque de cobro, agrupados por concepto ──
        $pendingCharges = Charge::query()
            ->with('concept')
            ->where('membership_account_id', $account->id)
            ->whereIn('status', ['pending', 'partial'])
            ->when(
                $cobroClubId,
                fn (Builder $q) => $q->whereHas('membership', fn (Builder $m) => $m->where('club_id', $cobroClubId))
            )
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $pendingConcepts = $pendingCharges
            ->groupBy('concept_id')
            ->map(function ($group) {
                /** @var \App\Models\Billing\ChargeConcept|null $concept */
                $concept = $group->first()->concept;
                $months = $group->count();
                $balance = round((float) $group->sum('balance'), 2);
                $originalTotal = round((float) $group->sum('amount'), 2);
                $isRecurring = (bool) ($concept?->is_recurring);

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
                    'charges' => $group->map(fn (Charge $charge) => [
                        'id' => $charge->id,
                        'balance' => round((float) $charge->balance, 2),
                        'allows_partial_payments' => (bool) $charge->allows_partial_payments,
                        'period_label' => $this->periodLabel($charge->period_month, $charge->period_year),
                    ])->values(),
                ];
            })
            ->values();

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
                'email' => $holder?->email,
                'phone' => $holder?->phone,
            ],
            'cobro_club' => $cobroClub ? [
                'id' => $cobroClub->id,
                'code' => $cobroClub->code,
                'name' => $cobroClub->name,
            ] : null,
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
                'payment_method_id' => ['required', new ExistsInSchema('billing', 'payment_methods', 'id')],
                'paid_at' => ['required', 'date'],
                'reference' => ['nullable', 'string', 'max:255'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'check_number' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],

                // Cargos existentes seleccionados
                'existing_charges' => ['array'],
                'existing_charges.*.charge_id' => ['required', new ExistsInSchema('billing', 'charges', 'id')],
                'existing_charges.*.amount' => ['required', 'numeric', 'gt:0'],

                // Conceptos nuevos capturados a mano
                'new_items' => ['array'],
                'new_items.*.concept_id' => ['required', new ExistsInSchema('billing', 'concepts', 'id')],
                'new_items.*.description' => ['nullable', 'string', 'max:255'],
                'new_items.*.total' => ['required', 'numeric', 'gt:0'],
            ]);

            $existing = collect($validated['existing_charges'] ?? []);
            $newItems = collect($validated['new_items'] ?? []);

            if ($existing->isEmpty() && $newItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'applications' => 'Agrega al menos un cargo o concepto a la lista de cobros.',
                ]);
            }

            $account = MembershipAccount::query()
                ->with('primaryHolder.member')
                ->findOrFail($validated['membership_account_id']);
            $clubId = (int) $validated['club_id'];
            $paymentMethod = PaymentMethod::query()
                ->where('id', $validated['payment_method_id'])
                ->where('is_active', true)
                ->firstOrFail();

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

            $payment = DB::transaction(function () use (
                $account, $clubId, $paymentMethod, $existing, $newItems,
                $membership, $memberId, $validated, $request
            ) {
                $applications = $existing
                    ->map(fn ($item) => [
                        'charge_id' => (int) $item['charge_id'],
                        'amount' => round((float) $item['amount'], 2),
                    ])
                    ->values()
                    ->all();

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

                return $this->paymentRegistrationService->register(
                    account: $account,
                    clubId: $clubId,
                    paymentMethod: $paymentMethod,
                    applications: $applications,
                    paidAt: $validated['paid_at'],
                    reference: $validated['reference'] ?? null,
                    bankName: $validated['bank_name'] ?? null,
                    checkNumber: $validated['check_number'] ?? null,
                    notes: $validated['notes'] ?? null,
                    receivedBy: $request->user()?->id,
                    sessionClubId: session('club_id'),
                );
            });

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Cobro registrado correctamente por $%s.',
                    number_format((float) $payment->amount, 2)
                ),
                'payment_id' => $payment->id,
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
