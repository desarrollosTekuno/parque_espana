<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotificationJob;
use App\Models\AdminClub\BusinessAd;
use App\Models\Administrator\Club;
use App\Models\Billing\AnnualDiscountRule;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Billing\ChargeConceptClubAmount;
use App\Models\Billing\CollectionNote;
use App\Models\Billing\PaymentMethod;
use App\Models\Members\Act;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\Membership;
use App\Models\Members\MemberDocument;
use App\Models\Memberships\MembershipAccount;
use App\Models\AdminClub\CafeteriaVisit;
use App\Rules\ExistsInSchema;
use App\Services\Access\GuestPassProvisioningService;
use App\Services\AdminClub\CafeteriaCheckoutService;
use App\Services\Billing\AnnualPaymentService;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\PaymentRegistrationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        protected AnnualPaymentService $annualPaymentService,
        protected GuestPassProvisioningService $guestPassProvisioningService
    ) {
    }

    /**
     * Pantalla principal del módulo. Los datos del socio se cargan de forma
     * asíncrona vía search(); aquí solo mandamos catálogos.
     */
    public function index()
    {
        // club_amounts: el monto configurado para cada parque (ver
        // ChargeConcept::resolveAmountForClub) — el frontend lo usa para
        // llenar el "Importe" al elegir un concepto en "Agregar concepto de
        // cobro" con el monto del parque en sesión, no el monto base
        // (default_amount), que solo aplica si no hay uno específico.
        // applies_iva: si el concepto factura IVA por default; club_amounts[].applies_iva:
        // override por parque (null = usa el default del concepto). Reemplaza
        // el criterio anterior de decidirlo por clubs.clubs.applies_iva a
        // nivel global — ver ChargeConcept::resolveAppliesIvaForClub.
        $conceptOptions = ChargeConcept::query()
            ->select('id', 'code', 'internal_key', 'name', 'default_amount', 'allows_manual_amount', 'is_recurring', 'allows_partial_payments', 'applies_iva', 'requires_account')
            ->with(['clubAmounts' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('internal_key')
            ->get()
            ->map(fn (ChargeConcept $concept) => [
                'id' => $concept->id,
                'code' => $concept->code,
                'internal_key' => $concept->internal_key,
                'name' => $concept->name,
                'default_amount' => $concept->default_amount,
                'allows_manual_amount' => $concept->allows_manual_amount,
                'is_recurring' => $concept->is_recurring,
                'allows_partial_payments' => $concept->allows_partial_payments,
                'applies_iva' => $concept->applies_iva,
                'requires_account' => $concept->requires_account,
                'club_amounts' => $concept->clubAmounts
                    ->map(fn (ChargeConceptClubAmount $ca) => [
                        'club_id' => $ca->club_id,
                        'amount' => $ca->amount,
                        'applies_iva' => $ca->applies_iva,
                    ])
                    ->values(),
            ]);

        return Inertia::render('Collections/Index', [
            'conceptOptions' => $conceptOptions,
            'clubPaymentMethods' => $this->resolveClubPaymentMethods(),
            // Meses (pay_by_month) con regla de descuento activa — el
            // frontend lo usa para solo mostrar el checkbox "¿Es pago de
            // anualidad?" cuando el mes de pago (o diciembre, que cubre el
            // año siguiente, ver resolveAnnualDiscountPaymentMonth) cae
            // dentro de un periodo configurado en BD. Fuera de esos
            // periodos no tiene caso ofrecer la anualidad.
            'annualDiscountRuleMonths' => AnnualDiscountRule::where('is_active', true)->pluck('pay_by_month')->values(),
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

        // La cuenta encontrada debe pertenecer al parque de la sesión, sin
        // excepción — aunque el socio sea interclub (tenga cuenta en ambos
        // parques, cada una es una MembershipAccount distinta con su propio
        // club_id). Si se busca por nombre y el socio tiene cuenta en varios
        // parques, la preferencia de orden de arriba ya elige la de este
        // parque; pero si se busca por la CLAVE específica de la cuenta del
        // OTRO parque, esa clave solo empata esa cuenta — y esa cuenta no es
        // de este parque, así que se rechaza igual que si el socio no fuera
        // interclub en absoluto.
        if ($sessionClubId && (int) $account->club_id !== $sessionClubId) {
            $ownClub = Club::find($account->club_id);

            return response()->json([
                'found' => false,
                'message' => $ownClub
                    ? "Esta cuenta pertenece a {$ownClub->code}, no al parque de tu sesión. Cambia de parque para poder cobrarla."
                    : 'Esta cuenta no pertenece al parque de tu sesión.',
            ]);
        }

        // Una cuenta dada de baja en ESTE parque no debe poder cobrarse
        // desde aquí — aunque el socio siga con membresía activa en el otro
        // parque (combo), esta cuenta específica ya no es operable. El
        // saldo pendiente que le corresponda (p. ej. un concepto de "ambos
        // parques" que también viva aquí) sigue siendo visible/cobrable
        // desde la cuenta del otro parque que sí sigue activa.
        if ($account->status === 'cancelled') {
            return response()->json([
                'found' => false,
                'message' => 'Esta cuenta está dada de baja en este parque.',
            ]);
        }

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

        // Membresía sobre la que se factura: la de la cuenta encontrada, que
        // el candado de arriba ya garantiza que es del parque de la sesión.
        $membership = Membership::query()
            ->where('membership_account_id', $account->id)
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->first();

        $cobroClub = $membership?->club
            ?? ($sessionClubId ? Club::find($sessionClubId) : null);
        $cobroClubId = $cobroClub?->id;

        // Rellena cualquier hueco de mensualidad hasta el mes en curso (ver
        // MembershipChargeService::ensureMonthlyChargesUpToToday) — solo la
        // membresía facturable genera cargos reales.
        $billableMembership = $accountMemberships->first(fn (Membership $m) => (bool) $m->is_billable)
            ?? $accountMemberships->first();

        if ($billableMembership) {
            $this->membershipChargeService->ensureMonthlyChargesUpToToday($billableMembership, $groupAccountIds);
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
        // Solo la mensualidad de "ambos parques" (MONTHLY_FEE_PARKS y
        // variantes — ver MembershipChargeService::MONTHLY_FEE_PARKS_CODES)
        // se muestra desde cualquier cuenta del grupo, ya que ese concepto
        // en sí representa una deuda compartida entre los dos parques y se
        // puede pagar desde cualquiera. La mensualidad de UN SOLO parque
        // (MONTHLY_FEE, MONTHLY_FEE_INTERMEDIATE, MONTHLY_FEE_PASS) es
        // propia de esa cuenta, igual que cualquier otro cargo (inscripción,
        // casilleros, etc.) — solo se muestra la de la cuenta que encontró
        // la búsqueda: de lo contrario, buscando desde el otro parque
        // aparecía también la mensualidad (o la inscripción) que no le
        // corresponde a ese parque.
        //
        // Solo la mensualidad (de cualquier tipo) cuenta como "pendiente
        // para cobrar hoy" nada más si ya venció (due_date <= hoy, o sin
        // due_date). "Agregar mensualidades" permite adelantar el pago de
        // meses futuros (hasta diciembre, ver resolveMonthlyFeeMonths) y
        // crea esos cargos de una vez aunque el cobro no se llegue a
        // confirmar; si eso pasa, no deben verse como adeudo "de hoy" — se
        // quedan esperando en la base de datos y reaparecen aquí solos en
        // cuanto llegue su fecha de vencimiento. Esto NO aplica a otros
        // conceptos (casilleros, etc.): esos sí deben poder cobrarse aunque
        // su vencimiento sea próximo, no tienen un mecanismo de "adelantar
        // pago" que deje huérfanos.
        $pendingCharges = Charge::query()
            ->with(['concept', 'membership.club'])
            ->whereIn('status', ['pending', 'partial'])
            ->where(function (Builder $query) use ($groupAccountIds, $account) {
                $query->where(
                    fn (Builder $comboMonthly) => $comboMonthly
                        ->whereIn('membership_account_id', $groupAccountIds)
                        ->whereHas('concept', fn (Builder $c) => $c->whereIn('code', MembershipChargeService::MONTHLY_FEE_PARKS_CODES))
                        ->where(fn (Builder $q) => $q->whereNull('due_date')->orWhere('due_date', '<=', now()->toDateString()))
                )->orWhere(
                    fn (Builder $ownParkMonthly) => $ownParkMonthly
                        ->where('membership_account_id', $account->id)
                        ->whereHas('concept', fn (Builder $c) => $c
                            ->whereIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES)
                            ->whereNotIn('code', MembershipChargeService::MONTHLY_FEE_PARKS_CODES))
                        ->where(fn (Builder $q) => $q->whereNull('due_date')->orWhere('due_date', '<=', now()->toDateString()))
                )->orWhere(
                    fn (Builder $other) => $other
                        ->where('membership_account_id', $account->id)
                        ->whereHas('concept', fn (Builder $c) => $c->whereNotIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES))
                );
            })
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        // El reparto entre parques solo aplica a la mensualidad (MONTHLY_FEE):
        // por eso solo esa se agrupa por concepto a través de todas las
        // cuentas del grupo, sin importar el periodo — un solo renglón con
        // todos los meses vencidos (como en el sistema anterior: "Cuota
        // mensualidad", meses vencidos, saldo total), no uno por mes. El
        // orden en que se van a pagar sigue viviendo en el arreglo "charges"
        // de ese renglón (ya viene ordenado cronológicamente por la consulta
        // base), para la mensualidad más antigua primero (ver
        // resolveMonthlyFeeMonths y la captura rápida "Agregar mensualidades").
        // Cualquier otro cargo (inscripción, casilleros, pase diario,
        // cafetería, etc.) se sigue agrupando por concepto + parque, para que
        // cada uno quede en su propio renglón, en el parque que le
        // corresponde, sin mezclarse ni marcarse como dividido.
        $billableMembership = $accountMemberships->first(fn (Membership $m) => (bool) $m->is_billable)
            ?? $accountMemberships->first();

        $pendingConcepts = $pendingCharges
            ->groupBy(function (Charge $charge) {
                if (in_array($charge->concept?->code, MembershipChargeService::MONTHLY_FEE_FAMILY_CODES, true)) {
                    return (string) $charge->concept_id;
                }

                return $charge->concept_id . '-' . ($charge->membership?->club_id ?? 'none');
            })
            ->map(function ($group) use ($billableMembership) {
                /** @var \App\Models\Billing\ChargeConcept|null $concept */
                $concept = $group->first()->concept;
                $isMonthlyFee = in_array($concept?->code, MembershipChargeService::MONTHLY_FEE_FAMILY_CODES, true);
                // Para la mensualidad, "meses" son los periodos distintos
                // vencidos (un mes puede traer 2 cargos si en algún momento
                // se repartió entre parques), no el número de cargos.
                $months = $isMonthlyFee
                    ? $group->groupBy(fn (Charge $c) => $c->period_year . '-' . $c->period_month)->count()
                    : $group->count();
                $balance = round((float) $group->sum('balance'), 2);
                $originalTotal = round((float) $group->sum('amount'), 2);
                $isRecurring = (bool) ($concept?->is_recurring);
                // La cuota que se muestra para la mensualidad es siempre la
                // vigente del año EN CURSO (no el promedio de los meses
                // vencidos, que puede mezclar años con cuotas distintas).
                $currentYearFee = $isMonthlyFee
                    ? $billableMembership?->resolveLiveMonthlyFee(now()->year)
                    : null;

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

                // Solo se sintetiza el reparto "Ambos parques" para
                // renglones cuyo PROPIO concepto ya representa combo
                // (MONTHLY_FEE_PARKS y variantes) — un renglón viejo bajo
                // un concepto de un solo parque (p. ej. MONTHLY_FEE de
                // antes de que existiera el combo) no debe etiquetarse así
                // solo porque la membresía HOY sí sea combo.
                $isParksConcept = in_array($concept?->code, MembershipChargeService::MONTHLY_FEE_PARKS_CODES, true);

                if ($isParksConcept && !$isMultiClub) {
                    // No se exige que el hermano siga activo: el concepto
                    // en sí (MONTHLY_FEE_PARKS y variantes) ya es "de ambos
                    // parques" — aunque la membresía del otro parque se
                    // haya dado de baja después, este cargo se sigue
                    // pudiendo cobrar desde cualquiera de los dos, igual
                    // que cuando se generó.
                    $comboBreakdown = $this->resolveHistoricalParksClubBreakdown($group->first()->membership, $balance);

                    if ($comboBreakdown) {
                        $isMultiClub = true;
                        $clubBreakdown = $comboBreakdown;
                    }
                }

                return [
                    'concept_id' => $concept?->id,
                    'concept_code' => $concept?->code,
                    'internal_key' => $concept?->internal_key,
                    // El nombre del concepto ya refleja la composición
                    // correcta (Cuota mensualidad / Cuota Mens Parques /
                    // Cuota Mens. Pase / etc.) porque se eligió al crear el
                    // cargo (ver MembershipChargeService::resolveMonthlyFeeConcept)
                    // — así el detalle de cargos pendientes dice lo mismo
                    // que después va a decir el ticket ya cobrado.
                    'concept_name' => $concept?->name,
                    // "tasa" queda vacía por ahora (a definir después).
                    'rate' => null,
                    // Cuota: para la mensualidad, la vigente del año en
                    // curso; para lo demás, el promedio del adeudo original.
                    'fee' => $isMonthlyFee
                        ? round((float) ($currentYearFee ?? ($months > 0 ? $originalTotal / $months : $originalTotal)), 2)
                        : ($months > 0 ? round($originalTotal / $months, 2) : $originalTotal),
                    'class_label' => $isRecurring ? 'A meses' : 'Una exhibición',
                    // Monto = adeudo dividido entre los meses que aplican.
                    'unit_amount' => $months > 0 ? round($balance / $months, 2) : $balance,
                    'months' => $months,
                    'balance' => $balance,
                    'is_multi_club' => $isMultiClub,
                    'club_breakdown' => $clubBreakdown,
                    // Un renglón de mensualidad ya representa varios meses a
                    // la vez, así que no hay un solo periodo que reportar.
                    'period_year' => null,
                    'period_month' => null,
                    'period_label' => null,
                    'charges' => $group->map(fn (Charge $charge) => [
                        'id' => $charge->id,
                        'balance' => round((float) $charge->balance, 2),
                        'allows_partial_payments' => (bool) $charge->allows_partial_payments,
                        'period_label' => $this->periodLabel($charge->period_month, $charge->period_year),
                        'club_id' => $charge->membership?->club_id,
                        'club_code' => $charge->membership?->club?->code,
                    ])->values(),
                    'is_up_to_date' => false,
                ];
            })
            ->values();

        // Si el socio no tiene ningún mes de mensualidad vencido/pendiente
        // (nada que cobrar hoy), la tabla de Cargos se queda sin ningún
        // renglón de mensualidad — pero el encargado de todos modos
        // necesita saber CUÁL concepto (01, 80, 80I, 81, etc.) le
        // corresponde para poder adelantar un mes desde "Agregar concepto
        // de cobro", sin tener que probarlos uno por uno en el selector.
        // Se agrega aquí un renglón puramente informativo (0 meses, $0 de
        // saldo, sin acción de "Agregar" — esa columna ya está deshabilitada
        // para toda la tabla) con el concepto que resolvería HOY según la
        // composición vigente (ver MembershipChargeService::resolveMonthlyFeeConcept).
        $hasPendingMonthlyRow = $pendingConcepts->contains(
            fn (array $row) => in_array($row['concept_code'], MembershipChargeService::MONTHLY_FEE_FAMILY_CODES, true)
        );

        if (!$hasPendingMonthlyRow && $billableMembership) {
            $applicableConcept = $this->membershipChargeService->resolveMonthlyFeeConcept($billableMembership);
            $currentYearFee = round((float) ($billableMembership->resolveLiveMonthlyFee(now()->year) ?? 0), 2);

            $pendingConcepts->push([
                'concept_id' => $applicableConcept->id,
                'concept_code' => $applicableConcept->code,
                'internal_key' => $applicableConcept->internal_key,
                'concept_name' => $applicableConcept->name,
                'rate' => null,
                'fee' => $currentYearFee,
                'class_label' => 'A meses',
                'unit_amount' => 0.0,
                'months' => 0,
                'balance' => 0.0,
                'is_multi_club' => false,
                'club_breakdown' => collect(),
                'period_year' => null,
                'period_month' => null,
                'period_label' => null,
                'charges' => collect(),
                // Marca este renglón como informativo (socio al corriente),
                // para que el frontend lo distinga de un adeudo real.
                'is_up_to_date' => true,
            ]);
        }

        // ── Resumen ──
        // Mismo criterio que la tabla de Cargos: solo el concepto de "ambos
        // parques" (MONTHLY_FEE_PARKS y variantes) se busca en TODO el
        // grupo de cuentas (puede vivir en la cuenta de la membresía
        // facturable, que no necesariamente es la que encontró la
        // búsqueda) — la mensualidad de un solo parque se acota a esta
        // cuenta, igual que en la tabla de Cargos.
        $lastPaid = Charge::query()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $combo) => $combo
                    ->whereHas('concept', fn (Builder $c) => $c->whereIn('code', MembershipChargeService::MONTHLY_FEE_PARKS_CODES))
                    ->whereIn('membership_account_id', $groupAccountIds))
                ->orWhere(fn (Builder $ownPark) => $ownPark
                    ->whereHas('concept', fn (Builder $c) => $c
                        ->whereIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES)
                        ->whereNotIn('code', MembershipChargeService::MONTHLY_FEE_PARKS_CODES))
                    ->where('membership_account_id', $account->id)))
            ->where('status', 'paid')
            ->whereNotNull('period_year')
            ->whereNotNull('period_month')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        $overdueMonths = Charge::query()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $combo) => $combo
                    ->whereHas('concept', fn (Builder $c) => $c->whereIn('code', MembershipChargeService::MONTHLY_FEE_PARKS_CODES))
                    ->whereIn('membership_account_id', $groupAccountIds))
                ->orWhere(fn (Builder $ownPark) => $ownPark
                    ->whereHas('concept', fn (Builder $c) => $c
                        ->whereIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES)
                        ->whereNotIn('code', MembershipChargeService::MONTHLY_FEE_PARKS_CODES))
                    ->where('membership_account_id', $account->id)))
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
            'related_accounts' => $this->resolveRelatedAccounts($account),
        ]);
    }

    /**
     * Cuentas relacionadas con la que se está cobrando por el árbol de
     * origen/derivadas (memberships.accounts.origin_account_id — mismo
     * mecanismo que la pestaña "Árbol" de Members/Show.vue, ver
     * MemberController::buildAccountTree). Es un mecanismo DISTINTO al de
     * account_group_id (mismo socio en varios parques, ver
     * resolveGroupAccountIds): aquí se trata de cuentas separadas que salieron
     * una de otra (p. ej. un hijo que se independizó a su propia cuenta),
     * frecuente que quien llega a pagar en mostrador termine cubriendo
     * también los cargos de esas cuentas. Se regresa aplanado (no como árbol
     * anidado) porque aquí solo hace falta elegir una cuenta para volver a
     * buscarla, no visualizar la jerarquía completa.
     *
     * @return array<int, array{id:int, membership_number:?string, internal_account_number:?string, holder_name:string, club_code:?string, status:?string}>
     */
    protected function resolveRelatedAccounts(MembershipAccount $account): array
    {
        $account->loadMissing(['originAccount.primaryHolder.member', 'originAccount.club']);

        $related = collect();

        if ($account->originAccount) {
            $related->push($account->originAccount);
        }

        $flatten = function (MembershipAccount $node) use (&$flatten, &$related) {
            $node->loadMissing(['derivedAccounts.primaryHolder.member', 'derivedAccounts.club']);

            foreach ($node->derivedAccounts as $child) {
                $related->push($child);
                $flatten($child);
            }
        };
        $flatten($account);

        return $related
            ->unique('id')
            ->map(function (MembershipAccount $related) {
                $holder = $related->primaryHolder?->member;

                return [
                    'id' => $related->id,
                    'membership_number' => $related->membership_number,
                    'internal_account_number' => $related->internal_account_number,
                    'holder_name' => trim(collect([
                        $holder?->first_name,
                        $holder?->last_name,
                        $holder?->second_last_name,
                    ])->filter()->implode(' ')) ?: '—',
                    'club_code' => $related->club?->code,
                    'status' => $related->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * "Agregar concepto de cobro" → cualquier concepto de la familia de
     * mensualidad (MONTHLY_FEE, MONTHLY_FEE_PARKS, etc. — ver
     * MembershipChargeService::MONTHLY_FEE_FAMILY_CODES): en vez de capturar
     * un importe a mano, el encargado solo indica cuántos meses quiere
     * agregar DEL CONCEPTO QUE SELECCIONÓ (concept_id). Se resuelven los N
     * meses más antiguos que el socio debe bajo ESE concepto específico,
     * empezando por el cargo más viejo que YA exista para su grupo de
     * cuentas (no la fecha de inicio real de la membresía, que puede ser de
     * hace años) y caminando mes a mes hasta hoy. Si algún mes de en medio
     * nunca se generó (hueco), se crea aquí mismo sobre la membresía
     * actualmente facturable — mismo mecanismo que usa el ciclo mensual
     * automático (MembershipChargeService::createRecurringMonthlyCharge),
     * solo que aplicado retroactivamente y bajo demanda.
     *
     * Un mismo periodo puede tener cargos de MÁS DE UN concepto (p. ej. uno
     * viejo bajo MONTHLY_FEE de antes del combo, y uno nuevo bajo
     * MONTHLY_FEE_PARKS del combo recién armado, ambos del mismo mes) — cada
     * concepto se agrega POR SEPARADO, nunca se fusionan en un solo total.
     * Si el concepto seleccionado ya no le corresponde a los meses
     * siguientes (la composición cambió), se deja de avanzar: ese concepto
     * ya no tiene más meses propios que adelantar.
     */
    public function resolveMonthlyFeeMonths(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_account_id' => ['required', new ExistsInSchema('memberships', 'accounts', 'id')],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'preview' => ['sometimes', 'boolean'],
            'concept_id' => ['required', new ExistsInSchema('billing', 'concepts', 'id')],
        ]);

        $preview = (bool) ($validated['preview'] ?? false);

        $account = MembershipAccount::findOrFail($validated['membership_account_id']);
        $groupAccountIds = $this->resolveGroupAccountIds($account);

        // El concepto elegido en el selector: cada uno de la familia de
        // mensualidad se resuelve y se paga POR SEPARADO — no se fusionan
        // en un solo total aunque coincidan en el mismo periodo. Esto
        // importa en la transición de un solo parque a combo: el mes en
        // que se agrega la segunda cuenta puede quedar con un cargo viejo
        // bajo el concepto de un solo parque (p. ej. MONTHLY_FEE) Y un
        // cargo nuevo bajo el concepto combo (p. ej. MONTHLY_FEE_PARKS)
        // para el mismo mes — cada uno es una deuda independiente.
        $selectedConcept = ChargeConcept::find($validated['concept_id']);

        if (!$selectedConcept || !in_array($selectedConcept->code, MembershipChargeService::MONTHLY_FEE_FAMILY_CODES, true)) {
            return response()->json(['message' => 'El concepto seleccionado no es de mensualidad.'], 422);
        }

        // Un concepto de "ambos parques" sí se busca en todo el grupo de
        // cuentas (puede vivir en la cuenta hermana) — uno de un solo
        // parque se acota SOLO a esta cuenta, igual que en la tabla de
        // Cargos: de lo contrario, si el socio también tiene mensualidad de
        // un solo parque en su otra cuenta (con el mismo código de
        // concepto, p. ej. MONTHLY_FEE en ambos lados antes de ser combo),
        // se mezclarían dos deudas de parques distintos.
        $isParksConcept = in_array($selectedConcept->code, MembershipChargeService::MONTHLY_FEE_PARKS_CODES, true);
        $scopedAccountIds = $isParksConcept ? $groupAccountIds : [$account->id];

        $memberships = Membership::query()
            ->with('club')
            ->whereIn('membership_account_id', $scopedAccountIds)
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->get();

        if ($memberships->isEmpty()) {
            return response()->json(['message' => 'El socio no tiene membresías activas.'], 422);
        }

        $billableMembership = $memberships->first(fn (Membership $m) => (bool) $m->is_billable)
            ?? $memberships->first();

        $earliestCharge = Charge::query()
            ->where('concept_id', $selectedConcept->id)
            ->whereIn('membership_account_id', $scopedAccountIds)
            ->whereNotNull('period_year')
            ->whereNotNull('period_month')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->first();

        $earliestChargePeriod = $earliestCharge
            ? Carbon::create((int) $earliestCharge->period_year, (int) $earliestCharge->period_month, 1)
            : null;
        $cursor = $this->membershipChargeService->resolveMonthlyBackfillStart($billableMembership, $earliestChargePeriod);
        $cancelledPeriods = $this->membershipChargeService->resolveCancelledPeriods($billableMembership);

        // Aquí sí se permite adelantar el pago de meses que todavía no
        // vencen (a diferencia del backfill automático al buscar al socio,
        // ver MembershipChargeService::ensureMonthlyChargesUpToToday, que
        // solo pone al día lo ya vencido) — pero nunca más allá de diciembre
        // del año en curso, porque la cuota del siguiente año es otra
        // captura (ver Cuotas por año) y todavía no existe.
        $latestChargeablePeriod = Carbon::create((int) now()->year, 12, 1);
        $resolved = [];
        $safetyLimit = 240; // ~20 años, para nunca quedar en un ciclo infinito por un dato inesperado.

        while ($cursor->lte($latestChargeablePeriod) && count($resolved) < $validated['months'] && $safetyLimit-- > 0) {
            if ($this->membershipChargeService->periodFallsWithin($cursor, $cancelledPeriods)) {
                $cursor->addMonthNoOverflow();
                continue;
            }

            // Cargos YA existentes de este periodo, pero solo del concepto
            // elegido — un mismo periodo puede tener cargos de más de un
            // concepto (p. ej. uno viejo bajo MONTHLY_FEE y uno nuevo bajo
            // MONTHLY_FEE_PARKS, cuando se agrega la segunda cuenta a medio
            // mes), y cada uno se paga por separado, no se fusionan.
            $periodCharges = Charge::query()
                ->with('membership.club')
                ->where('concept_id', $selectedConcept->id)
                ->whereIn('membership_account_id', $scopedAccountIds)
                ->where('period_year', $cursor->year)
                ->where('period_month', $cursor->month)
                ->where('status', '!=', 'cancelled')
                ->get();

            if ($periodCharges->isEmpty()) {
                // No hay cargo de ESTE concepto para el periodo — antes de
                // crear uno nuevo, hay que confirmar que el concepto
                // seleccionado sigue siendo el que en verdad le toca a este
                // periodo (según la composición vigente en ese momento, ver
                // resolveMonthlyFeeConcept). Si ya no coincide (p. ej. se
                // seleccionó el concepto de un solo parque, pero para este
                // periodo ya existe combo), no se sigue avanzando: ese
                // concepto ya no tiene más meses propios por adelantar —
                // los meses futuros le tocan al concepto combo, que se
                // agrega aparte.
                $expectedConcept = $this->membershipChargeService->resolveMonthlyFeeConcept($billableMembership, $cursor->copy());

                if ($expectedConcept->id !== $selectedConcept->id) {
                    break;
                }

                // En modo preview (cálculo en vivo mientras se captura la
                // cantidad) no se persiste nada todavía — solo se calcula el
                // monto que tocaría ese mes. La creación real del cargo
                // faltante se difiere hasta que el encargado confirme
                // "Agregar" (preview=false), para no generar cargos en la
                // base de datos solo por escribir un número.
                if ($preview) {
                    $amount = round(
                        $this->membershipChargeService->previewMonthlyFeeAmount($billableMembership, $cursor->copy()),
                        2
                    );

                    if ($amount > 0) {
                        $resolved[] = [
                            'balance' => $amount,
                            'period_label' => $this->periodLabel($cursor->month, $cursor->year),
                            'club_code' => $billableMembership->club?->code,
                            'is_virtual' => true,
                            'charge_breakdown' => [],
                        ];
                    }

                    $cursor->addMonthNoOverflow();

                    continue;
                }

                $this->membershipChargeService->createRecurringMonthlyCharge(
                    membership: $billableMembership,
                    periodDate: $cursor->copy(),
                    metadata: [
                        'charge_origin' => 'collections_desk_backfill',
                        'created_by' => $request->user()?->id,
                    ],
                );

                $periodCharges = Charge::query()
                    ->with('membership.club')
                    ->where('concept_id', $selectedConcept->id)
                    ->whereIn('membership_account_id', $scopedAccountIds)
                    ->where('period_year', $cursor->year)
                    ->where('period_month', $cursor->month)
                    ->where('status', '!=', 'cancelled')
                    ->get();
            }

            $payableCharges = $periodCharges->whereIn('status', ['pending', 'partial']);

            if ($payableCharges->isNotEmpty()) {
                $resolved[] = [
                    'balance' => round((float) $payableCharges->sum('balance'), 2),
                    'period_label' => $this->periodLabel($cursor->month, $cursor->year),
                    'club_code' => $payableCharges->first()->membership?->club?->code,
                    'is_virtual' => false,
                    'charge_breakdown' => $payableCharges->map(fn (Charge $c) => [
                        'id' => $c->id,
                        'balance' => round((float) $c->balance, 2),
                    ])->values(),
                ];
            }

            $cursor->addMonthNoOverflow();
        }

        if (empty($resolved)) {
            return response()->json(['message' => 'No hay mensualidades pendientes por agregar.'], 422);
        }

        $total = round(collect($resolved)->sum('balance'), 2);
        // El reparto "Ambos parques" (para el diálogo de métodos de pago)
        // solo aplica si el concepto que en verdad se está agregando es de
        // los que representan combo — uno de un solo parque (p. ej. el
        // MONTHLY_FEE viejo de antes del combo) no debe repartirse 50/50.
        // Se usa resolveHistoricalParksClubBreakdown (no
        // resolveComboClubBreakdown): el concepto seleccionado YA es combo
        // por construcción, así que el reparto no debe depender de si la
        // membresía hermana sigue activa hoy — igual que en search().
        $comboBreakdown = $isParksConcept
            ? $this->resolveHistoricalParksClubBreakdown($billableMembership, $total)
            : null;

        return response()->json([
            'charges' => collect($resolved)->values(),
            'total' => $total,
            'is_multi_club' => (bool) $comboBreakdown,
            'club_breakdown' => $comboBreakdown ?? collect(),
            'concept_label' => $selectedConcept->name,
        ]);
    }

    /**
     * Previsualiza "Pagar anualidad": cubre desde el mes vencido más viejo
     * (aunque sea de un año anterior) hasta diciembre del año en el que se
     * está pagando, aplicando el descuento por anualidad vigente (ver
     * billing.annual_discount_rules / AnnualDiscountRule::findApplicable)
     * según el mes en que se realiza el pago. El descuento se calcula
     * siempre sobre la cuota del año que se cubre — el adeudo de años
     * anteriores, si lo hay, se cobra completo, sin prorratear el
     * descuento sobre él.
     */
    public function previewAnnualPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_account_id' => ['required', new ExistsInSchema('memberships', 'accounts', 'id')],
            'paid_at' => ['required', 'date'],
            // Año que se quiere cubrir — normalmente se infiere de paid_at,
            // pero en diciembre la anualidad del año SIGUIENTE ya se puede
            // ir cubriendo por adelantado (ver resolveAnnualCoverageYear).
            'year' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $account = MembershipAccount::findOrFail($validated['membership_account_id']);
        $groupAccountIds = $this->resolveGroupAccountIds($account);
        $paidAt = Carbon::parse($validated['paid_at']);
        $year = $this->resolveAnnualCoverageYear($paidAt, $validated['year'] ?? null);

        $billableMembership = $this->resolveBillableGroupMembership($groupAccountIds);

        if (!$billableMembership) {
            return response()->json(['message' => 'El socio no tiene una membresía facturable activa.'], 422);
        }

        $monthlyFeeConceptIds = $this->membershipChargeService->resolveMonthlyFeeFamilyConceptIds();

        $priorYearsBalance = round((float) Charge::query()
            ->whereIn('membership_account_id', $groupAccountIds)
            ->whereIn('concept_id', $monthlyFeeConceptIds)
            ->where('period_year', '<', $year)
            ->whereIn('status', ['pending', 'partial'])
            ->sum('balance'), 2);

        $existingCharges = Charge::query()
            ->whereIn('membership_account_id', $groupAccountIds)
            ->whereIn('concept_id', $monthlyFeeConceptIds)
            ->where('period_year', $year)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('period_month');

        $months = [];
        $currentYearBalance = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            $existing = $existingCharges->get($month);
            $balance = $existing
                ? (in_array($existing->status, ['pending', 'partial'], true) ? round((float) $existing->balance, 2) : 0.0)
                : round($this->membershipChargeService->previewMonthlyFeeAmount($billableMembership, Carbon::create($year, $month, 1)), 2);

            $currentYearBalance += $balance;
            $months[] = [
                'month' => $month,
                'period_label' => $this->periodLabel($month, $year),
                'balance' => $balance,
                'is_virtual' => !$existing,
                'is_paid' => (bool) ($existing && $existing->status === 'paid'),
            ];
        }
        $currentYearBalance = round($currentYearBalance, 2);

        $monthlyFee = round((float) ($billableMembership->monthly_fee_share ?? $billableMembership->monthly_fee ?? 0), 2);
        $rule = AnnualDiscountRule::findApplicable($this->resolveAnnualDiscountPaymentMonth($paidAt, $year));
        $discountAmount = $rule ? round($monthlyFee * (float) $rule->discount_months, 2) : 0.0;
        $totalBalance = round($priorYearsBalance + $currentYearBalance, 2);
        $paymentAmount = round(max($totalBalance - $discountAmount, 0), 2);

        // La anualidad es, en el fondo, una colección de mensualidades — si
        // la membresía facturable representa un combo interclub (mismo
        // criterio que resolveComboClubBreakdown, usado para "Agregar
        // mensualidades"), el pago también debe poder repartirse 50/50
        // entre los métodos de pago de ambos parques en el diálogo, no solo
        // los del parque de la sesión.
        $comboBreakdown = $this->resolveComboClubBreakdown($billableMembership, $paymentAmount);

        return response()->json([
            'year' => $year,
            // Años que se pueden cubrir con esta fecha de pago — solo trae
            // más de uno en diciembre (el actual, por si falta cerrarlo, y
            // el siguiente, para adelantar su anualidad). El frontend usa
            // esto para decidir si debe mostrar el selector de año.
            'coverage_year_options' => $this->resolveAnnualCoverageYearOptions($paidAt),
            'months' => $months,
            'prior_years_balance' => $priorYearsBalance,
            'current_year_balance' => $currentYearBalance,
            'total_balance' => $totalBalance,
            'monthly_fee' => $monthlyFee,
            'discount_rule' => $rule ? [
                'pay_by_month' => $rule->pay_by_month,
                'discount_months' => $rule->discount_months,
                'free_month' => $rule->free_month,
            ] : null,
            'discount_amount' => $discountAmount,
            'payment_amount' => $paymentAmount,
            'is_multi_club' => (bool) $comboBreakdown,
            'club_breakdown' => $comboBreakdown ?? collect(),
            'concept_label' => $this->membershipChargeService->resolveMonthlyFeeConcept($billableMembership)->name,
        ]);
    }

    /**
     * La membresía primaria activa/suspendida y facturable dentro de las
     * cuentas dadas (propia + grupo combo si aplica) — la que realmente
     * carga la mensualidad real, sin importar en cuál de las cuentas viva.
     */
    protected function resolveBillableGroupMembership(array $groupAccountIds): ?Membership
    {
        return Membership::query()
            ->whereIn('membership_account_id', $groupAccountIds)
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->where('is_billable', true)
            ->first();
    }

    /**
     * Año que se cubre con la anualidad. Normalmente el de la fecha de
     * pago, pero si se paga en diciembre, la anualidad del año SIGUIENTE ya
     * se puede ir cubriendo por adelantado (empieza a venderse desde
     * diciembre del año anterior) — el cajero puede elegir explícitamente
     * cuál de los dos quiere cubrir vía $requestedYear (por si en diciembre
     * todavía falta cerrar el año en curso en vez de adelantar el próximo);
     * si no indica nada, se asume el siguiente (lo más común en diciembre).
     */
    protected function resolveAnnualCoverageYear(Carbon $paidAt, ?int $requestedYear): int
    {
        $options = $this->resolveAnnualCoverageYearOptions($paidAt);
        $defaultYear = $options[count($options) - 1]; // el más reciente: el siguiente en diciembre, si no el actual.

        if ($requestedYear === null) {
            return $defaultYear;
        }

        return in_array($requestedYear, $options, true) ? $requestedYear : $defaultYear;
    }

    /**
     * @return array<int, int>
     */
    protected function resolveAnnualCoverageYearOptions(Carbon $paidAt): array
    {
        return $paidAt->month === 12
            ? [$paidAt->year, $paidAt->year + 1]
            : [$paidAt->year];
    }

    /**
     * Mes que se usa para buscar la regla de descuento
     * (AnnualDiscountRule::findApplicable) — normalmente el mes calendario
     * del pago, salvo cuando se paga en diciembre del año ANTERIOR al que
     * se está cubriendo: ahí se usa 0 ("antes de que empiece el año"), que
     * por construcción de la regla (pay_by_month >= mes de pago, ordenado
     * ascendente) automáticamente califica para AL MENOS el descuento de
     * enero, sin necesidad de una regla explícita para el mes 0 — a menos
     * que se quiera dar un descuento todavía mejor por pagar con tanta
     * anticipación, en cuyo caso sí se puede capturar una regla con
     * pay_by_month=0 desde Cuotas por año.
     */
    protected function resolveAnnualDiscountPaymentMonth(Carbon $paidAt, int $year): int
    {
        if ($paidAt->year === $year - 1 && $paidAt->month === 12) {
            return 0;
        }

        return $paidAt->month;
    }

    /**
     * "Agregar concepto de cobro" → código INSCRIPTION o CUOTA_REINSCRIPCION:
     * este módulo solo cobra, no decide diferir a meses (eso se define en el
     * alta de la cuenta o en la reactivación — ver
     * MembershipChargeService::createInitialCharges /
     * ::createInstallmentCharge, $installmentMonths — que ya crean ahí los N
     * cargos si aplica). Aquí el encargado solo indica cuántos de esos
     * cargos ya existentes quiere cobrar ahora: si no se difirió, siempre
     * hay uno solo; si sí se difirió, puede haber varios y se resuelven los
     * más antiguos primero (mismo criterio que resolveMonthlyFeeMonths). No
     * crea nada, solo resuelve cargos que ya existen.
     */
    public function resolveInscriptionInstallments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_account_id' => ['required', new ExistsInSchema('memberships', 'accounts', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'concept_code' => ['sometimes', 'string', Rule::in(array_merge(
                MembershipChargeService::INSCRIPTION_FAMILY_CODES,
                ['CHEQUE_REBOTADO_PARQUE2', 'CHEQUE_REBOTADO_PARQUE1', 'COMISION_CHEQUE_REBOTADO']
            ))],
        ]);

        $conceptCode = $validated['concept_code'] ?? 'INSCRIPTION';
        $account = MembershipAccount::findOrFail($validated['membership_account_id']);
        $concept = ChargeConcept::where('code', $conceptCode)->first();

        if (!$concept) {
            return response()->json(['message' => "No existe el concepto {$conceptCode}."], 422);
        }

        // Solo la mensualidad se busca en todo el grupo de cuentas del
        // socio (ver search()); la inscripción es propia de un solo parque,
        // así que se resuelve nada más sobre la cuenta que está cobrando.
        $allCharges = Charge::query()
            ->where('concept_id', $concept->id)
            ->where('membership_account_id', $account->id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($allCharges->isEmpty()) {
            return response()->json(['message' => "No hay cargos de {$concept->name} pendientes."], 422);
        }

        $selected = $allCharges->take($validated['quantity']);

        return response()->json([
            'charges' => $selected->map(fn (Charge $c) => [
                'id' => $c->id,
                'balance' => round((float) $c->balance, 2),
            ])->values(),
            'total' => round((float) $selected->sum('balance'), 2),
            'available_count' => $allCharges->count(),
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
                // Nulo para una venta "sin cuenta" (ver abajo): un concepto
                // marcado billing.concepts.requires_account=false, cobrado a
                // un visitante sin socio ligado (p. ej. un pase diario).
                'membership_account_id' => ['nullable', new ExistsInSchema('memberships', 'accounts', 'id')],
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
                'payments.*.is_park_split' => ['sometimes', 'boolean'],
                'payments.*.club_id' => ['sometimes', 'nullable', new ExistsInSchema('clubs', 'clubs', 'id')],

                // Cargos existentes seleccionados
                'existing_charges' => ['array'],
                'existing_charges.*.charge_id' => ['required', new ExistsInSchema('billing', 'charges', 'id')],
                'existing_charges.*.amount' => ['required', 'numeric', 'gt:0'],

                // Conceptos nuevos capturados a mano
                'new_items' => ['array'],
                'new_items.*.concept_id' => ['required', new ExistsInSchema('billing', 'concepts', 'id')],
                'new_items.*.description' => ['nullable', 'string', 'max:255'],
                'new_items.*.total' => ['required', 'numeric', 'gt:0'],
                'new_items.*.quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'new_items.*.unit_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],

                // Salidas de cafetería capturadas en "Agregar concepto de
                // cobro": la visita solo se da por cerrada (y el cargo, si
                // aplica, solo se genera) hasta que se confirme este pago.
                'cafeteria_checkouts' => ['array'],
                'cafeteria_checkouts.*.visit_id' => ['required', new ExistsInSchema('guest_lists', 'cafeteria_visits', 'id')],
                'cafeteria_checkouts.*.consumption_amount' => ['required', 'numeric', 'min:0'],

                // Pago de anualidad (checkbox "¿Es pago de anualidad?" en
                // el panel de mensualidad, ver Collections/Index.vue) — se
                // puede combinar en el mismo cobro con cualquiera de los
                // otros tres bloques (p. ej. junto con un pase diario), a
                // diferencia de existing_charges de mensualidad suelta, que
                // el frontend ya bloquea mezclar con esto (ver
                // clearAnnualLineIfPresent).
                'annual' => ['sometimes', 'nullable', 'array'],
                'annual.year' => ['required_with:annual', 'integer', 'min:2000', 'max:2100'],
            ]);

            $existing = collect($validated['existing_charges'] ?? []);
            $newItems = collect($validated['new_items'] ?? []);
            $cafeteriaCheckouts = collect($validated['cafeteria_checkouts'] ?? []);
            $annualRequest = $validated['annual'] ?? null;

            if ($existing->isEmpty() && $newItems->isEmpty() && $cafeteriaCheckouts->isEmpty() && !$annualRequest) {
                throw ValidationException::withMessages([
                    'applications' => 'Agrega al menos un cargo o concepto a la lista de cobros.',
                ]);
            }

            $clubId = (int) $validated['club_id'];
            $accountId = $validated['membership_account_id'] ?? null;

            $paymentMethodIds = collect($validated['payments'])->pluck('payment_method_id')->unique()->values();
            $activePaymentMethodCount = PaymentMethod::whereIn('id', $paymentMethodIds)->where('is_active', true)->count();

            if ($activePaymentMethodCount !== $paymentMethodIds->count()) {
                throw ValidationException::withMessages([
                    'payments' => 'Una o más formas de pago seleccionadas no están activas.',
                ]);
            }

            if ($accountId === null) {
                // Venta "sin cuenta": no hay socio de por medio, así que no
                // tiene sentido traer cargos ya existentes (son propios de
                // una cuenta) ni salidas de cafetería (tienen su propio
                // flujo de visita) — solo conceptos nuevos, y únicamente los
                // marcados requires_account=false.
                if ($existing->isNotEmpty() || $cafeteriaCheckouts->isNotEmpty() || $annualRequest) {
                    throw ValidationException::withMessages([
                        'membership_account_id' => 'Una venta sin cuenta solo puede incluir conceptos nuevos que no requieran cuenta.',
                    ]);
                }

                $invalidConceptId = $newItems->pluck('concept_id')->unique()->first(
                    fn ($conceptId) => !ChargeConcept::where('id', $conceptId)->where('requires_account', false)->exists()
                );

                if ($invalidConceptId !== null) {
                    throw ValidationException::withMessages([
                        'new_items' => 'Uno o más conceptos seleccionados requieren una cuenta de socio.',
                    ]);
                }

                $account = null;
                $groupAccountIds = [];
                $accountClubIds = [];
                $membership = null;
                $memberId = null;
            } else {
                $account = MembershipAccount::query()
                    ->with('primaryHolder.member')
                    ->findOrFail($accountId);

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

                // No se puede liquidar solo la mensualidad si el socio todavía
                // debe la inscripción/reinscripción — a diferencia de la
                // mensualidad (que sí considera todo el grupo, porque un
                // combo interclub se liquida en un solo pago), la
                // inscripción es propia de cada parque, así que solo se
                // revisa la cuenta de este parque (ver search(), que ya
                // garantiza que $account pertenece al parque de la sesión).
                $this->ensurePendingInscriptionIsPaidWithMonthlyFee(
                    $existing->pluck('charge_id')->map(fn ($id) => (int) $id),
                    isPayingMonthlyFee: $annualRequest !== null || Charge::query()
                        ->whereIn('id', $existing->pluck('charge_id'))
                        ->whereHas('concept', fn (Builder $q) => $q->whereIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES))
                        ->exists(),
                    accountIds: [$account->id]
                );

                // Sin filtrar por status: aunque una de las membresías del grupo
                // ya se haya dado de baja, sus cargos de mensualidad pendientes
                // de ANTES de la baja siguen siendo cobrables — si aquí solo se
                // consideraran las membresías activas, ensureChargesBelongToClub
                // rechazaría esos cargos viejos por "pertenecer a otro parque"
                // en cuanto se cancelara cualquiera de los dos lados del combo.
                $accountClubIds = Membership::query()
                    ->whereIn('membership_account_id', $groupAccountIds)
                    ->where('is_primary', true)
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
            }

            $annualYear = null;
            $annualRule = null;

            if ($annualRequest) {
                $billableMembership = $this->resolveBillableGroupMembership($groupAccountIds);

                if (!$billableMembership) {
                    throw ValidationException::withMessages([
                        'annual' => 'El socio no tiene una membresía facturable activa.',
                    ]);
                }

                $paidAt = Carbon::parse($validated['paid_at']);
                $annualYear = $this->resolveAnnualCoverageYear($paidAt, $annualRequest['year']);
                $annualRule = AnnualDiscountRule::findApplicable($this->resolveAnnualDiscountPaymentMonth($paidAt, $annualYear));

                // Rellena cualquier mes faltante hasta diciembre del año que
                // se está cubriendo — createRecurringMonthlyCharge ya es
                // idempotente (no duplica si el periodo ya tiene cargo, ver
                // hasMonthlyChargeForPeriod).
                for ($month = 1; $month <= 12; $month++) {
                    $this->membershipChargeService->createRecurringMonthlyCharge(
                        $billableMembership,
                        Carbon::create($annualYear, $month, 1),
                        ['charge_origin' => 'annual_payment']
                    );
                }
            }

            $payments = DB::transaction(function () use (
                $account, $clubId, $existing, $newItems, $cafeteriaCheckouts,
                $membership, $memberId, $validated, $request, $accountClubIds,
                $groupAccountIds, $annualYear, $annualRule
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
                    $concept = ChargeConcept::find($item['concept_id']);
                    $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;

                    // Si el concepto no permite capturar el importe a mano
                    // (billing.concepts.allows_manual_amount=false, ver
                    // BillingConcepts/Index.vue), se ignora por completo lo
                    // que haya mandado el cliente y se recalcula aquí con el
                    // monto configurado del parque — así un valor manipulado
                    // en la petición no puede colar un importe distinto al
                    // configurado.
                    if ($concept && !$concept->allows_manual_amount) {
                        $unitAmount = $concept->resolveAmountForClub($clubId);

                        if ($unitAmount === null) {
                            throw ValidationException::withMessages([
                                'new_items' => "El concepto {$concept->name} no tiene un monto configurado.",
                            ]);
                        }

                        $unitAmount = round($unitAmount, 2);
                        $total = round($unitAmount * max($quantity, 1), 2);
                        $item['unit_amount'] = $unitAmount;
                    } else {
                        $total = round((float) $item['total'], 2);
                    }

                    $charge = Charge::create([
                        'membership_account_id' => $account?->id,
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
                            // Cantidad/importe unitario capturados en el
                            // panel (p. ej. "3 x $300.00 = $900.00" de un
                            // pase diario) — se guardan para que el ticket
                            // los muestre desglosados (ver
                            // PaymentTicketService::concepts) en vez de
                            // solo "1 x $900.00".
                            'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : null,
                            'unit_amount' => isset($item['unit_amount']) ? round((float) $item['unit_amount'], 2) : null,
                        ],
                    ]);

                    $applications[] = [
                        'charge_id' => $charge->id,
                        'amount' => $total,
                    ];

                    // Conceptos "20" (pase diario) y "22" (pase infantil): cada unidad de
                    // quantity representa una tarjeta física distinta que hay que generar en commands
                    if (in_array($concept?->code, ['20', '22'], true))
                    {
                        $accountMemberId = $account ? $account->primaryHolder?->id : null;
                        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;

                        for ($i = 0; $i < $quantity; $i++) {
                            $this->guestPassProvisioningService->provisionDayPass(
                                clubId: $clubId,
                                validUntil: now()->addDay(),
                                accountMemberId: $accountMemberId,
                                chargeId: $charge->id
                            );
                        }
                    }
                }

                // Pago de anualidad: se agrega a la MISMA lista de
                // aplicaciones que el resto (cargos existentes, conceptos
                // nuevos, cafetería) — un solo Payment/payment_group_id
                // cubre todo. El descuento (si aplica una regla) queda
                // amarrado al cargo del "mes libre" vía la clave 'discount'
                // (ver PaymentRegistrationService::registerSplit, que la
                // guarda en billing.payment_applications.discount).
                if ($annualYear !== null) {
                    $annual = $this->annualPaymentService->resolveApplications($groupAccountIds, $annualYear, $annualRule);

                    if (empty($annual['applications'])) {
                        throw ValidationException::withMessages([
                            'annual' => "No se encontraron cargos de mensualidad pendientes hasta {$annualYear}.",
                        ]);
                    }

                    $applications = [...$applications, ...$annual['applications']];
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

            // Anuncios de negocio: si algún cargo existente pagado
            // corresponde a un anuncio ya aprobado y pendiente de pago
            // (BusinessAdController::approve, que deja status_id=3 y
            // metadata.business_ad_id en el cargo), se marca como
            // publicado — mismo criterio que BillingController::storePayment.
            $existingChargeIds = $existing->pluck('charge_id')->map(fn ($id) => (int) $id);

            if ($existingChargeIds->isNotEmpty()) {
                $businessAdIds = Charge::whereIn('id', $existingChargeIds)
                    ->get()
                    ->pluck('metadata.business_ad_id')
                    ->filter()
                    ->unique();

                if ($businessAdIds->isNotEmpty()) {
                    BusinessAd::whereIn('id', $businessAdIds)
                        ->where('status_id', 3)
                        ->update([
                            'status_id' => 5,
                            'paid_at' => now(),
                            'published_at' => now(),
                            'expires_at' => now()->addMonth(),
                        ]);
                }
            }

            // Notificación push al titular de la cuenta (asíncrona vía
            // queue) — mismo criterio que BillingController::storePayment.
            // No aplica en una venta "sin cuenta" (walk-in), ahí no hay
            // ningún socio a quien notificar.
            $userId = $account?->primaryHolder?->member?->user_id;
            if ($userId) {
                SendPushNotificationJob::dispatch(
                    $userId,
                    'Pago registrado',
                    sprintf('Se registró un pago de $%s en tu cuenta.', number_format($totalPaid, 2)),
                    ['screen' => 'AccountStatement', 'type' => 'account_statement', 'club_id' => (string) $clubId],
                );
            }

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
    /**
     * Cuentas del mismo account_group_id que la dada — PERO solo cuando ese
     * grupo representa un combo interclub real (algún paquete específico,
     * interclub_package_rule_id, o una regla genérica marcada
     * requires_multiple_clubs — mismo criterio que
     * MemberController::resolveGroupBillingSummary). Dos cuentas pueden
     * compartir grupo sin que exista esa relación de precio (p. ej. el
     * mismo titular con un Individual en un parque y un Pase Mensual
     * Individual en otro, cada uno con su propio pricing_rule
     * independiente) — ahí cada cuenta debe cobrarse por su lado, no
     * mezclarse: antes esto hacía que la mensualidad de ambas cuentas se
     * juntara en un solo renglón (y un solo total) en Cobranza aunque no
     * hubiera ningún paquete que las combinara de verdad.
     */
    protected function resolveGroupAccountIds(MembershipAccount $account): array
    {
        if (!$account->account_group_id) {
            return [$account->id];
        }

        $groupAccountIds = MembershipAccount::query()
            ->where('account_group_id', $account->account_group_id)
            ->pluck('id')
            ->all();

        if (count($groupAccountIds) <= 1) {
            return $groupAccountIds;
        }

        $representsCombo = Membership::query()
            ->whereIn('membership_account_id', $groupAccountIds)
            ->where('is_primary', true)
            ->where(function (Builder $scope) {
                $scope->whereNotNull('interclub_package_rule_id')
                    ->orWhereHas('pricingRule', fn (Builder $pricingRule) => $pricingRule->where('requires_multiple_clubs', true));
            })
            ->exists();

        return $representsCombo ? $groupAccountIds : [$account->id];
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
            ->whereHas('concept', fn (Builder $q) => $q->whereIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES))
            ->get(['id', 'period_year', 'period_month']);

        if ($chargesBeingPaid->isEmpty()) {
            return;
        }

        $latestPeriodBeingPaid = $chargesBeingPaid
            ->max(fn (Charge $c) => ($c->period_year ?? 0) * 100 + ($c->period_month ?? 0));

        $missingEarlier = Charge::query()
            ->whereIn('membership_account_id', $groupAccountIds)
            ->whereHas('concept', fn (Builder $q) => $q->whereIn('code', MembershipChargeService::MONTHLY_FEE_FAMILY_CODES))
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

    /**
     * Lanza una ValidationException si el pago liquida mensualidad (cargos
     * existentes de MONTHLY_FEE, o un pago de anualidad) sin incluir ningún
     * cargo de inscripción/reinscripción, cuando el socio tiene inscripción
     * pendiente en $accountIds — a diferencia de la mensualidad (que sí se
     * revisa a nivel de todo el grupo interclub), la inscripción es propia
     * de cada parque, así que aquí normalmente solo se pasa la cuenta del
     * parque en sesión, no todo el grupo. No se puede pagar solo mensualidad
     * ignorando por completo la inscripción. Como la inscripción también se
     * puede diferir a meses (ver resolveInscriptionInstallments), basta con
     * incluir AL MENOS uno de esos cargos en este mismo pago — no hace falta
     * liquidar todos los meses de inscripción pendientes de una sola vez,
     * igual que la mensualidad tampoco exige pagar todos los meses
     * pendientes juntos.
     */
    protected function ensurePendingInscriptionIsPaidWithMonthlyFee(
        \Illuminate\Support\Collection $existingChargeIds,
        bool $isPayingMonthlyFee,
        array $accountIds
    ): void {
        if (!$isPayingMonthlyFee) {
            return;
        }

        $hasPendingInscription = Charge::query()
            ->whereIn('membership_account_id', $accountIds)
            ->whereHas('concept', fn (Builder $q) => $q->whereIn('code', MembershipChargeService::INSCRIPTION_FAMILY_CODES))
            ->whereIn('status', ['pending', 'partial'])
            ->exists();

        if (!$hasPendingInscription) {
            return;
        }

        $includesInscriptionCharge = Charge::query()
            ->whereIn('id', $existingChargeIds)
            ->whereHas('concept', fn (Builder $q) => $q->whereIn('code', MembershipChargeService::INSCRIPTION_FAMILY_CODES))
            ->exists();

        if (!$includesInscriptionCharge) {
            throw ValidationException::withMessages([
                'existing_charges' => 'El socio tiene inscripción o reinscripción pendiente. Agrega al menos un cargo de inscripción a este pago antes de liquidar solo la mensualidad.',
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
     * La mensualidad de un combo interclub (Individual+Familiar, etc.) ya se
     * genera como UN SOLO cargo, en la cuenta del parque que quedó
     * facturable (ver
     * MembershipPricingService::recalculateGroupFeesAfterReactivation) — no
     * dos cargos repartidos como en el modo "equal_split" (deshabilitado).
     * Aun así, para que el cajero pueda repartir el pago en Cheque/Tarjeta de
     * crédito entre ambos parques (ver PaymentMethodsDialog.vue), se arma un
     * reparto 50/50 "virtual" cuando la membresía facturable representa un
     * combo — no cambia dónde se registra el cargo real, solo qué métodos de
     * pago se ofrecen y en qué proporción. El combo puede venir de un
     * paquete interclub específico (interclub_package_rule_id) o de la regla
     * genérica por tipo marcada requires_multiple_clubs=true (rama sin
     * paquete específico capturado en recalculateGroupFeesAfterReactivation)
     * — cualquiera de las dos representa una cuota combinada entre parques.
     *
     * @return \Illuminate\Support\Collection|null null si la membresía
     *  facturable no representa un combo interclub o no tiene hermano activo.
     */
    private function resolveComboClubBreakdown(?Membership $billableMembership, float $balance): ?\Illuminate\Support\Collection
    {
        if (!$billableMembership) {
            return null;
        }

        $representsCombo = (bool) $billableMembership->interclub_package_rule_id
            || (bool) ($billableMembership->pricingRule?->requires_multiple_clubs ?? false);

        if (!$representsCombo) {
            return null;
        }

        $siblingMembership = Membership::query()
            ->with('club')
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->where('club_id', '!=', $billableMembership->club_id)
            ->whereHas('account', fn (Builder $q) => $q->where(
                'account_group_id',
                $billableMembership->account?->account_group_id
            ))
            ->first();

        if (!$siblingMembership?->club) {
            return null;
        }

        $siblingShare = round($balance / 2, 2);

        return collect([
            [
                'club_id' => $billableMembership->club_id,
                'club_code' => $billableMembership->club?->code,
                'club_name' => $billableMembership->club?->name,
                'amount' => round($balance - $siblingShare, 2),
            ],
            [
                'club_id' => $siblingMembership->club->id,
                'club_code' => $siblingMembership->club->code,
                'club_name' => $siblingMembership->club->name,
                'amount' => $siblingShare,
            ],
        ]);
    }

    /**
     * Reparto 50/50 para un cobro (ya existente, o todavía "virtual"/por
     * generar, ver resolveMonthlyFeeMonths) cuyo propio concepto YA es de la
     * familia "ambos parques" (MONTHLY_FEE_PARKS y variantes) — a
     * diferencia de resolveComboClubBreakdown (que decide si un cobro NUEVO
     * debe repartirse según si el combo sigue vigente HOY), aquí el
     * concepto ya es combo por construcción: no importa si la membresía del
     * otro parque se dio de baja después de generarse — se sigue pudiendo
     * cobrar desde cualquiera de los dos parques, igual que cuando se
     * generó. Por eso el hermano se busca sin filtrar por status.
     */
    private function resolveHistoricalParksClubBreakdown(?Membership $ownMembership, float $balance): ?\Illuminate\Support\Collection
    {
        if (!$ownMembership) {
            return null;
        }

        $accountGroupId = $ownMembership->account?->account_group_id;

        if (!$accountGroupId) {
            return null;
        }

        $siblingMembership = Membership::query()
            ->with('club')
            ->where('is_primary', true)
            ->where('club_id', '!=', $ownMembership->club_id)
            ->whereHas('account', fn (Builder $q) => $q->where('account_group_id', $accountGroupId))
            ->first();

        if (!$siblingMembership?->club) {
            return null;
        }

        $siblingShare = round($balance / 2, 2);

        return collect([
            [
                'club_id' => $ownMembership->club_id,
                'club_code' => $ownMembership->club?->code,
                'club_name' => $ownMembership->club?->name,
                'amount' => round($balance - $siblingShare, 2),
            ],
            [
                'club_id' => $siblingMembership->club->id,
                'club_code' => $siblingMembership->club->code,
                'club_name' => $siblingMembership->club->name,
                'amount' => $siblingShare,
            ],
        ]);
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
