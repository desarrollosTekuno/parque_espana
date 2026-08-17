<?php

namespace App\Services\Billing;

use App\Models\Memberships\AbsencePermit;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MembershipChargeService
{
    public function synchronizeMembershipFees(
        Membership $membership,
        ?float $groupTotalMonthlyFee = null,
        ?Carbon $effectiveDate = null,
        ?string $billingSplitMode = null,
        ?string $historyReason = null,
        ?int $pricingRuleId = null,
        ?int $interclubPackageRuleId = null
    ): Collection {
        $referenceDate = ($effectiveDate ?? now())->copy()->startOfDay();
        $groupMemberships = $this->resolveGroupPrimaryMemberships($membership, $referenceDate);
        $billingSplitMode = $billingSplitMode ?? $membership->billing_split_mode ?? 'single';

        if ($groupMemberships->isEmpty()) {
            $singleTotal = round($groupTotalMonthlyFee ?? $this->resolveMembershipMonthlyFeeTotal($membership), 2);
            $previousFee = round((float) $membership->monthly_fee, 2);

            $membership->update([
                'monthly_fee' => $singleTotal,
                'monthly_fee_total' => $singleTotal,
                'monthly_fee_share' => $singleTotal,
                'billing_split_mode' => 'single',
                'is_billable' => $singleTotal > 0,
                'pricing_rule_id' => $pricingRuleId,
                'interclub_package_rule_id' => $interclubPackageRuleId,
            ]);

            if ($historyReason && abs($singleTotal - $previousFee) > 0.01) {
                $this->insertFeeHistory($membership, $previousFee, $singleTotal, $historyReason);
            }

            return collect([$membership->fresh(['membershipType', 'account.primaryHolder.member', 'club'])]);
        }

        if ($this->shouldSplitMonthlyChargesAcrossGroup($groupMemberships, $billingSplitMode)) {
            $groupTotal = round(
                $groupTotalMonthlyFee ?? $this->resolveGroupMonthlyTotal($groupMemberships, $this->resolveMembershipMonthlyFeeTotal($membership)),
                2
            );
            $membershipCount = $groupMemberships->count();
            $splitAmount = round($groupTotal / $membershipCount, 2);
            $allocated = 0.0;
            $lastMembership = $groupMemberships->last();

            foreach ($groupMemberships as $groupMembership) {
                $share = $groupMembership->is($lastMembership)
                    ? round($groupTotal - $allocated, 2)
                    : $splitAmount;

                $allocated = round($allocated + $share, 2);
                $previousFee = round((float) $groupMembership->monthly_fee, 2);

                $groupMembership->update(array_merge([
                    'monthly_fee' => $groupTotal,
                    'monthly_fee_total' => $groupTotal,
                    'monthly_fee_share' => $share,
                    'billing_split_mode' => 'equal_split',
                    'is_billable' => $share > 0,
                ], $groupMembership->is($membership) ? [
                    'pricing_rule_id' => $pricingRuleId,
                    'interclub_package_rule_id' => $interclubPackageRuleId,
                ] : []));

                if ($historyReason && abs($groupTotal - $previousFee) > 0.01) {
                    $this->insertFeeHistory($groupMembership, $previousFee, $groupTotal, $historyReason);
                }
            }

            return $groupMemberships->map(fn (Membership $groupMembership) => $groupMembership->fresh(['membershipType', 'account.primaryHolder.member', 'club']));
        }

        foreach ($groupMemberships as $groupMembership) {
            $total = round(
                $groupMembership->is($membership)
                    ? ($groupTotalMonthlyFee ?? $this->resolveMembershipMonthlyFeeTotal($groupMembership))
                    : $this->resolveMembershipMonthlyFeeTotal($groupMembership),
                2
            );
            $previousFee = round((float) $groupMembership->monthly_fee, 2);

            $groupMembership->update(array_merge([
                'monthly_fee' => $total,
                'monthly_fee_total' => $total,
                'monthly_fee_share' => $total,
                'billing_split_mode' => 'single',
                // Solo la membresía que disparó esta sincronización puede cambiar
                // su estado de facturable/no facturable aquí. Las demás del grupo
                // conservan el suyo (fue decidido explícitamente al crearlas — ver
                // MemberController::shouldSourceMembershipBecomeNonBillable) para
                // no reactivar por accidente un cobro duplicado en el otro parque.
                'is_billable' => $groupMembership->is($membership) ? ($total > 0) : (bool) $groupMembership->is_billable,
            ], $groupMembership->is($membership) ? [
                'pricing_rule_id' => $pricingRuleId,
                'interclub_package_rule_id' => $interclubPackageRuleId,
            ] : []));

            if ($historyReason && abs($total - $previousFee) > 0.01) {
                $this->insertFeeHistory($groupMembership, $previousFee, $total, $historyReason);
            }
        }

        return $groupMemberships->map(fn (Membership $groupMembership) => $groupMembership->fresh(['membershipType', 'account.primaryHolder.member', 'club']));
    }

    private function insertFeeHistory(Membership $membership, float $previousFee, float $newFee, string $reason): void
    {
        DB::table('memberships.membership_history')->insert([
            'membership_id'          => $membership->id,
            'old_membership_type_id' => $membership->membership_type_id,
            'new_membership_type_id' => $membership->membership_type_id,
            'changed_by'             => auth()->id(),
            'effective_date'         => now()->toDateString(),
            'reason'                 => $reason,
            'previous_monthly_fee'   => $previousFee,
            'new_monthly_fee'        => $newFee,
            'metadata'               => json_encode(['fee_recalculation' => true]),
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    public function createRecurringMonthlyCharge(
        Membership $membership,
        ?Carbon $periodDate = null,
        array $metadata = [],
        ?float $monthlyFeeOverride = null,
        bool $ignoreBillableState = false
    ): bool {
        $chargeDate = ($periodDate ?? now())->copy()->startOfMonth();
        $monthlyConcept = $this->resolveConcept('MONTHLY_FEE');

        if (!$ignoreBillableState && !(bool) $membership->is_billable) {
            return false;
        }

        // Punto único de control: cualquier camino que genere mensualidad
        // recurrente pasa por aquí (backfill al buscar al socio, "Agregar
        // mensualidades" en Collections, y el comando de ciclo mensual
        // memberships:generate-monthly-charges) — sin esto, un camino que no
        // revisara esto por su cuenta podía resucitar mensualidad de un
        // periodo ya condonado o de cuando la cuenta estuvo dada de baja.
        $backfillFloor = $this->resolveBackfillFloorMonth($membership);
        if ($backfillFloor && $chargeDate->lt($backfillFloor)) {
            return false;
        }

        if ($this->periodFallsWithin($chargeDate, $this->resolveCancelledPeriods($membership))) {
            return false;
        }

        if ($this->hasMonthlyChargeForPeriod($membership, $chargeDate, $monthlyConcept->id)) {
            return false;
        }

        $monthlyFee = round((float) ($monthlyFeeOverride ?? $this->resolveMembershipMonthlyFeeShare($membership, null, $chargeDate->year, $chargeDate)), 2);
        $effectiveMonthlyFee = $this->resolveAbsenceAdjustedMonthlyFee(
            membership: $membership,
            monthlyFee: $monthlyFee,
            chargeDate: $chargeDate
        );

        if ($effectiveMonthlyFee <= 0) {
            return false;
        }

        Charge::create([
            'membership_account_id' => $membership->membership_account_id,
            'membership_id' => $membership->id,
            'member_id' => $membership->account?->primaryHolder?->member_id,
            'concept_id' => $monthlyConcept->id,
            'description' => $this->buildMonthlyChargeDescription($membership, $chargeDate),
            'amount' => $effectiveMonthlyFee,
            'balance' => $effectiveMonthlyFee,
            'issue_date' => $chargeDate->toDateString(),
            'due_date' => $this->resolveMonthlyDueDate($chargeDate)->toDateString(),
            'period_year' => (int) $chargeDate->format('Y'),
            'period_month' => (int) $chargeDate->format('m'),
            'allows_partial_payments' => (bool) $monthlyConcept->allows_partial_payments,
            'status' => 'pending',
            'metadata' => array_merge($metadata, [
                'concept_code' => $monthlyConcept->code,
                'target_monthly_fee' => $monthlyFee,
                'monthly_fee_total' => $this->resolveMembershipMonthlyFeeTotal($membership, null, $chargeDate->year),
                'monthly_fee_share' => $monthlyFee,
                'effective_monthly_fee' => $effectiveMonthlyFee,
                'generation_type' => 'monthly_cycle',
                'generated_at' => now()->toDateTimeString(),
            ]),
        ]);

        return true;
    }

    /**
     * Calcula (sin persistir nada) el monto que se cobraría por esta
     * membresía en el periodo indicado — misma resolución de cuota que usa
     * createRecurringMonthlyCharge. Sirve para previsualizar meses que
     * todavía no tienen cargo generado (p. ej. el cálculo en vivo del
     * módulo de cobranza) sin crear cargos reales solo por calcular.
     */
    public function previewMonthlyFeeAmount(Membership $membership, Carbon $periodDate): float
    {
        $chargeDate = $periodDate->copy()->startOfMonth();
        $monthlyFee = round(
            $this->resolveMembershipMonthlyFeeShare($membership, null, $chargeDate->year, $chargeDate),
            2
        );

        return $this->resolveAbsenceAdjustedMonthlyFee(
            membership: $membership,
            monthlyFee: $monthlyFee,
            chargeDate: $chargeDate
        );
    }

    /**
     * Recalcula los cargos de mensualidad YA EXISTENTES que caigan dentro
     * del rango de un permiso por ausencia (al registrarlo o al
     * cancelarlo) — sin esto, un cargo generado ANTES de registrar el
     * permiso (p. ej. por el backfill automático al buscar al socio en
     * Cobranza) se queda con el monto completo para siempre, aunque el
     * permiso ya esté vigente para ese mes: previewMonthlyFeeAmount /
     * createRecurringMonthlyCharge solo aplican el descuento a cargos que
     * TODAVÍA no existen.
     *
     * Solo toca cargos en status 'pending' (balance == amount, nada
     * aplicado todavía) — uno 'partial' o 'paid' ya tiene dinero real de
     * por medio, ajustarlo ahí correspondería a una cancelación de pago,
     * no a esto.
     */
    public function reconcilePendingMonthlyChargesForAbsencePermit(
        ?int $accountGroupId,
        ?int $membershipAccountId,
        Carbon $startDate,
        Carbon $endDate
    ): void {
        $accountIds = $accountGroupId
            ? MembershipAccount::where('account_group_id', $accountGroupId)->pluck('id')->all()
            : array_filter([$membershipAccountId]);

        if (empty($accountIds)) {
            return;
        }

        $monthlyConcept = $this->resolveConcept('MONTHLY_FEE');
        $periodStart = $startDate->copy()->startOfMonth();
        $periodEnd = $endDate->copy()->startOfMonth();

        Charge::query()
            ->with('membership')
            ->where('concept_id', $monthlyConcept->id)
            ->whereIn('membership_account_id', $accountIds)
            ->where('status', 'pending')
            ->whereNotNull('period_year')
            ->whereNotNull('period_month')
            ->get()
            ->each(function (Charge $charge) use ($periodStart, $periodEnd) {
                $period = Carbon::create((int) $charge->period_year, (int) $charge->period_month, 1);

                if ($period->lt($periodStart) || $period->gt($periodEnd) || !$charge->membership) {
                    return;
                }

                $newAmount = $this->previewMonthlyFeeAmount($charge->membership, $period);

                if (round($newAmount, 2) === round((float) $charge->amount, 2)) {
                    return;
                }

                $charge->update([
                    'amount' => $newAmount,
                    'balance' => $newAmount,
                    'metadata' => array_merge($charge->metadata ?? [], [
                        'absence_permit_reconciled_at' => now()->toDateTimeString(),
                    ]),
                ]);
            });
    }

    public function hasMonthlyChargeForPeriod(
        Membership $membership,
        ?Carbon $periodDate = null,
        ?int $conceptId = null
    ): bool {
        $chargeDate = ($periodDate ?? now())->copy()->startOfMonth();
        $conceptId ??= $this->resolveConcept('MONTHLY_FEE')->id;

        return Charge::query()
            ->where('membership_id', $membership->id)
            ->where('concept_id', $conceptId)
            ->where('period_year', (int) $chargeDate->format('Y'))
            ->where('period_month', (int) $chargeDate->format('m'))
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    /**
     * Garantiza que existan cargos de mensualidad para TODOS los meses entre
     * el cargo más antiguo que ya exista para el grupo de cuentas del socio
     * (cualquiera de sus parques) y el mes en curso, creando aquí mismo
     * cualquier hueco de en medio — no solo el siguiente periodo. Antes se
     * usaba resolveNextChargeablePeriod()/ensureMonthlyChargeForNextPeriod(),
     * que al no haber ningún cargo 'paid' regresaba el mismo periodo del
     * cargo pendiente más antiguo una y otra vez, así que una membresía con
     * un solo mes vencido desde hace más de un año se quedaba "atorada" en
     * ese único mes en vez de ir generando los siguientes.
     *
     * Si el grupo nunca ha tenido ningún cargo, solo se crea el del mes en
     * curso (no se resucitan años de historial para una membresía sin
     * cargos previos, p. ej. una migrada sin ese dato).
     */
    public function ensureMonthlyChargesUpToToday(Membership $billableMembership, array $groupAccountIds): void
    {
        $monthlyConcept = $this->resolveConcept('MONTHLY_FEE');
        $currentPeriod = now()->startOfMonth();

        $earliestCharge = Charge::query()
            ->where('concept_id', $monthlyConcept->id)
            ->whereIn('membership_account_id', $groupAccountIds)
            ->whereNotNull('period_year')
            ->whereNotNull('period_month')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->first();

        if (!$earliestCharge) {
            $this->createRecurringMonthlyCharge($billableMembership, $currentPeriod);

            return;
        }

        $earliestChargePeriod = Carbon::create((int) $earliestCharge->period_year, (int) $earliestCharge->period_month, 1);
        $cursor = $this->resolveMonthlyBackfillStart($billableMembership, $earliestChargePeriod);
        $safetyLimit = 240; // ~20 años, para nunca quedar en un ciclo infinito por un dato inesperado.
        $cancelledPeriods = $this->resolveCancelledPeriods($billableMembership);

        while ($cursor->lte($currentPeriod) && $safetyLimit-- > 0) {
            $wasCancelledThisPeriod = $this->periodFallsWithin($cursor, $cancelledPeriods);

            if ($wasCancelledThisPeriod) {
                $cursor->addMonthNoOverflow();
                continue;
            }

            $existsForPeriod = Charge::query()
                ->where('concept_id', $monthlyConcept->id)
                ->whereIn('membership_account_id', $groupAccountIds)
                ->where('period_year', $cursor->year)
                ->where('period_month', $cursor->month)
                ->where('status', '!=', 'cancelled')
                ->exists();

            if (!$existsForPeriod) {
                $this->createRecurringMonthlyCharge($billableMembership, $cursor->copy(), [
                    'charge_origin' => 'auto_backfill_on_search',
                ]);
            }

            $cursor->addMonthNoOverflow();
        }
    }

    /**
     * Meses en los que la membresía estuvo dada de baja, reconstruidos a
     * partir de memberships.membership_history. Sin esto, ensureMonthlyChargesUpToToday
     * rellenaba mensualidad para TODO el hueco entre el cargo más antiguo y hoy,
     * incluyendo los meses en que la cuenta estuvo cancelada — generando adeudo
     * por periodos en los que el socio no tenía membresía activa.
     *
     * @return array<int, array{0: Carbon, 1: ?Carbon}> pares [inicio, fin) del mes en que se dio de baja / reactivó
     */
    public function resolveCancelledPeriods(Membership $membership): array
    {
        $events = DB::table('memberships.membership_history')
            ->where('membership_id', $membership->id)
            ->whereIn('reason', ['Baja voluntaria de cuenta', 'Reactivación de cuenta'])
            ->orderBy('created_at')
            ->get(['reason', 'effective_date']);

        $periods = [];
        $cancelledFrom = null;

        foreach ($events as $event) {
            $effectiveMonth = Carbon::parse($event->effective_date)->startOfMonth();

            if ($event->reason === 'Baja voluntaria de cuenta') {
                $cancelledFrom ??= $effectiveMonth;
            } elseif ($event->reason === 'Reactivación de cuenta' && $cancelledFrom) {
                $periods[] = [$cancelledFrom, $effectiveMonth];
                $cancelledFrom = null;
            }
        }

        if ($cancelledFrom) {
            $periods[] = [$cancelledFrom, null];
        }

        return $periods;
    }

    /**
     * @param array<int, array{0: Carbon, 1: ?Carbon}> $periods
     */
    public function periodFallsWithin(Carbon $month, array $periods): bool
    {
        foreach ($periods as [$start, $end]) {
            if ($month->gte($start) && ($end === null || $month->lt($end))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mes a partir del cual se puede volver a rellenar mensualidad faltante
     * para esta membresía — null si nunca se condonó adeudo. Ver
     * memberships.accounts.billing_backfill_floor y
     * AccountCancellationController::waivePendingCharges.
     */
    public function resolveBackfillFloorMonth(Membership $membership): ?Carbon
    {
        $floor = $membership->account?->billing_backfill_floor;

        return $floor ? $floor->copy()->startOfMonth() : null;
    }

    /**
     * Punto de partida para recorrer mes a mes al rellenar mensualidad
     * faltante: el periodo del cargo más antiguo existente, nunca antes del
     * piso de condonación (si aplica). Compartido entre el backfill
     * automático (ensureMonthlyChargesUpToToday) y el de "Agregar concepto
     * de cobro" en Collections (CollectionController::resolveMonthlyFeeMonths).
     */
    public function resolveMonthlyBackfillStart(Membership $membership, ?Carbon $earliestChargePeriod): Carbon
    {
        $cursor = ($earliestChargePeriod ?? now())->copy()->startOfMonth();
        $floor = $this->resolveBackfillFloorMonth($membership);

        if ($floor && $cursor->lt($floor)) {
            return $floor;
        }

        return $cursor;
    }

    public function createInitialCharges(
        Membership $membership,
        float $monthlyFee,
        float $inscriptionFee = 0,
        array $metadata = [],
        ?Carbon $chargeDate = null,
        bool $reconcileExistingMonthlyCharge = false,
        ?int $installmentMonths = null
    ): void {
        $chargeDate = ($chargeDate ?? now())->copy()->startOfDay();
        $monthlyConcept = $this->resolveConcept('MONTHLY_FEE');
        $groupMemberships = $this->resolveGroupPrimaryMemberships($membership, $chargeDate);
        $splitAcrossGroup = $this->shouldSplitMonthlyChargesAcrossGroup($groupMemberships);

        if ($splitAcrossGroup) {
            // Todo socio con membresía activa en más de un parque debe tener
            // su mensualidad repartida entre ambos — sin importar si alguna
            // membresía del grupo ya tenía un cargo del periodo (p. ej. venía
            // de antes de sumar el segundo parque): a cada una se le cobra
            // solo la diferencia hasta cubrir su mitad, nunca el total del
            // grupo completo (ver ensureSplitMonthlyChargesForGroup).
            $groupTotalMonthlyFee = $this->resolveGroupMonthlyTotal(
                memberships: $groupMemberships,
                candidateMonthlyFee: $monthlyFee
            );

            $this->ensureSplitMonthlyChargesForGroup(
                groupMemberships: $groupMemberships,
                groupTotalMonthlyFee: $groupTotalMonthlyFee,
                chargeDate: $chargeDate,
                concept: $monthlyConcept,
                metadata: $metadata
            );
        } else {
            $existingPeriodMonthlyAmount = $reconcileExistingMonthlyCharge
                ? $this->resolveExistingPeriodMonthlyAmount(
                    membership: $membership,
                    conceptId: $monthlyConcept->id,
                    chargeDate: $chargeDate
                )
                : 0.0;

            $effectiveMonthlyFee = $this->resolveAbsenceAdjustedMonthlyFee(
                membership: $membership,
                monthlyFee: $this->resolveMembershipMonthlyFeeShare($membership, $monthlyFee, $chargeDate->year),
                chargeDate: $chargeDate
            );

            if (((bool) $membership->is_billable || $reconcileExistingMonthlyCharge) && $effectiveMonthlyFee > 0) {
                $monthlyChargeAmount = $effectiveMonthlyFee;
                $monthlyChargeDescription = $this->buildMonthlyChargeDescription($membership, $chargeDate);

                if ($reconcileExistingMonthlyCharge) {
                    $monthlyChargeAmount = round($effectiveMonthlyFee - $existingPeriodMonthlyAmount, 2);

                    if ($existingPeriodMonthlyAmount > 0 && $monthlyChargeAmount > 0) {
                        $monthlyChargeDescription = $this->buildMonthlyAdjustmentChargeDescription(
                            membership: $membership,
                            chargeDate: $chargeDate,
                            totalMonthlyFee: $effectiveMonthlyFee
                        );
                    }
                }

                if ($monthlyChargeAmount > 0) {
                    $this->storeMonthlyCharge(
                        membership: $membership,
                        concept: $monthlyConcept,
                        chargeDate: $chargeDate,
                        amount: $monthlyChargeAmount,
                        targetMonthlyFee: $this->resolveMembershipMonthlyFeeShare($membership, $monthlyFee, $chargeDate->year),
                        effectiveMonthlyFee: $effectiveMonthlyFee,
                        description: $monthlyChargeDescription,
                        metadata: array_merge($metadata, [
                            'is_monthly_adjustment' => $reconcileExistingMonthlyCharge,
                        ]),
                        dueDate: $chargeDate
                    );
                }
            }
        }

        if ($inscriptionFee > 0) {
            $this->createInstallmentCharge(
                membership: $membership,
                conceptCode: 'INSCRIPTION',
                totalAmount: $inscriptionFee,
                installmentMonths: $installmentMonths,
                metadata: $metadata,
                chargeDate: $chargeDate
            );
        }
    }

    /**
     * Crea un cargo (o varios, si $installmentMonths > 1) para un concepto
     * de una sola vez, independiente de la lógica de mensualidad. Extraído
     * de createInitialCharges para poder reutilizarse con otros conceptos
     * de una sola vez (p. ej. CUOTA_REINSCRIPCION en reactivación de cuenta)
     * sin volver a disparar la creación/reconciliación del cargo mensual.
     */
    public function createInstallmentCharge(
        Membership $membership,
        string $conceptCode,
        float $totalAmount,
        ?int $installmentMonths = null,
        array $metadata = [],
        ?Carbon $chargeDate = null
    ): void {
        if ($totalAmount <= 0) {
            return;
        }

        $chargeDate = ($chargeDate ?? now())->copy()->startOfDay();
        $concept = $this->resolveConcept($conceptCode);
        $months = ($installmentMonths !== null && $installmentMonths > 1) ? $installmentMonths : 1;
        $baseAmount = round($totalAmount / $months, 2);
        $remainder = round($totalAmount - ($baseAmount * $months), 2);

        $commonFields = [
            'membership_account_id' => $membership->membership_account_id,
            'membership_id'         => $membership->id,
            'member_id'             => $membership->account?->primaryHolder?->member_id,
            'concept_id'            => $concept->id,
            'period_year'           => null,
            'period_month'          => null,
            'allows_partial_payments' => (bool) $concept->allows_partial_payments,
            'status'                => 'pending',
        ];

        for ($i = 0; $i < $months; $i++) {
            $dueDate = $chargeDate->copy()->addMonthsNoOverflow($i);
            $amount  = $i === $months - 1
                ? round($baseAmount + $remainder, 2)
                : $baseAmount;

            Charge::create(array_merge($commonFields, [
                'description' => $months > 1
                    ? $this->buildInstallmentChargeDescription($concept, $membership, $i + 1, $months)
                    : $this->buildSingleChargeDescription($concept, $membership),
                'amount'     => $amount,
                'balance'    => $amount,
                'issue_date' => $chargeDate->toDateString(),
                'due_date'   => $dueDate->toDateString(),
                'metadata'   => array_merge($metadata, [
                    'concept_code'       => $concept->code,
                    'installment_months' => $months > 1 ? $months : null,
                    'installment_index'  => $months > 1 ? $i + 1 : null,
                ]),
            ]));
        }
    }

    protected function resolveConcept(string $code): ChargeConcept
    {
        return ChargeConcept::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();
    }

    protected function resolveMonthlyDueDate(Carbon $chargeDate): Carbon
    {
        return $chargeDate->copy()->day(min(10, $chargeDate->daysInMonth));
    }

    protected function storeMonthlyCharge(
        Membership $membership,
        ChargeConcept $concept,
        Carbon $chargeDate,
        float $amount,
        float $targetMonthlyFee,
        float $effectiveMonthlyFee,
        string $description,
        array $metadata = [],
        ?Carbon $dueDate = null
    ): void {
        Charge::create([
            'membership_account_id' => $membership->membership_account_id,
            'membership_id' => $membership->id,
            'member_id' => $membership->account?->primaryHolder?->member_id,
            'concept_id' => $concept->id,
            'description' => $description,
            'amount' => $amount,
            'balance' => $amount,
            'issue_date' => $chargeDate->toDateString(),
            'due_date' => ($dueDate ?? $chargeDate)->toDateString(),
            'period_year' => (int) $chargeDate->format('Y'),
            'period_month' => (int) $chargeDate->format('m'),
            'allows_partial_payments' => (bool) $concept->allows_partial_payments,
            'status' => 'pending',
            'metadata' => array_merge($metadata, [
                'concept_code' => $concept->code,
                'target_monthly_fee' => $targetMonthlyFee,
                'monthly_fee_total' => $this->resolveMembershipMonthlyFeeTotal($membership, null, $chargeDate->year),
                'monthly_fee_share' => $targetMonthlyFee,
                'effective_monthly_fee' => $effectiveMonthlyFee,
            ]),
        ]);
    }

    protected function buildMonthlyChargeDescription(Membership $membership, Carbon $chargeDate): string
    {
        $monthLabel = $chargeDate->locale('es')->translatedFormat('F Y');
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresía';

        return sprintf('Mensualidad %s - %s', ucfirst($monthLabel), $membershipTypeName);
    }

    protected function buildMonthlyAdjustmentChargeDescription(
        Membership $membership,
        Carbon $chargeDate,
        float $totalMonthlyFee
    ): string {
        $monthLabel = $chargeDate->locale('es')->translatedFormat('F Y');
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresía';

        return sprintf(
            'Complemento de mensualidad %s - %s (total del período $%s)',
            ucfirst($monthLabel),
            $membershipTypeName,
            number_format($totalMonthlyFee, 2)
        );
    }

    protected function buildSingleChargeDescription(ChargeConcept $concept, Membership $membership): string
    {
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresía';

        return sprintf('%s - %s', $concept->name, $membershipTypeName);
    }

    protected function buildInstallmentChargeDescription(ChargeConcept $concept, Membership $membership, int $index, int $total): string
    {
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresía';

        return sprintf('%s - %s (Parcialidad %d de %d)', $concept->name, $membershipTypeName, $index, $total);
    }

    protected function resolveExistingPeriodMonthlyAmount(
        Membership $membership,
        int $conceptId,
        Carbon $chargeDate
    ): float {
        $accountIds = $this->resolveComboAwareAccountIds($membership);

        return (float) Charge::query()
            ->whereIn('membership_account_id', $accountIds)
            ->where('concept_id', $conceptId)
            ->where('period_year', (int) $chargeDate->format('Y'))
            ->where('period_month', (int) $chargeDate->format('m'))
            ->where('status', '!=', 'cancelled')
            ->sum('amount');
    }

    /**
     * Cuentas del mismo account_group_id que la membresía dada — solo
     * cuando ese grupo representa un combo interclub real
     * (interclub_package_rule_id o un pricing_rule con
     * requires_multiple_clubs=true en alguna membresía primaria del
     * grupo). Mismo criterio que CollectionController::resolveGroupAccountIds
     * y MemberController::resolveGroupBillingSummary — dos cuentas pueden
     * compartir grupo (mismo titular) sin que exista esa relación de
     * precio, y ahí cada una debe tratarse por separado. Sin este filtro,
     * resolveExistingPeriodMonthlyAmount sumaba el cargo del periodo de
     * CUALQUIER cuenta hermana, aunque no tuviera nada que ver con la
     * mensualidad de esta membresía — restando de más al calcular el
     * ajuste de reactivación/transición.
     */
    protected function resolveComboAwareAccountIds(Membership $membership): Collection
    {
        $accountGroupId = $membership->account?->account_group_id;

        if (!$accountGroupId) {
            return collect([$membership->membership_account_id]);
        }

        $groupAccountIds = MembershipAccount::query()
            ->where('account_group_id', $accountGroupId)
            ->pluck('id');

        if ($groupAccountIds->count() <= 1) {
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

        return $representsCombo ? $groupAccountIds : collect([$membership->membership_account_id]);
    }

    protected function resolveGroupPrimaryMemberships(Membership $membership, Carbon $chargeDate): Collection
    {
        $accountGroupId = $membership->account?->account_group_id;

        if (!$accountGroupId) {
            return collect([$membership]);
        }

        $periodStart = $chargeDate->copy()->startOfMonth()->toDateString();
        $periodEnd = $chargeDate->copy()->endOfMonth()->toDateString();

        return Membership::query()
            ->with(['membershipType', 'account.primaryHolder.member', 'club'])
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('account', function ($query) use ($accountGroupId) {
                $query->where('account_group_id', $accountGroupId);
            })
            ->where(function ($query) use ($periodEnd) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $periodEnd);
            })
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $periodStart);
            })
            ->orderBy('club_id')
            ->orderBy('id')
            ->get();
    }

    protected function shouldSplitMonthlyChargesAcrossGroup(Collection $memberships, ?string $billingSplitMode = null): bool
    {
        if ($memberships->count() < 2) {
            return false;
        }

        $resolvedMode = $billingSplitMode
            ?? $memberships->pluck('billing_split_mode')->filter()->first()
            ?? 'single';

        if ($resolvedMode !== 'equal_split') {
            return false;
        }

        return $memberships->pluck('club_id')->filter()->unique()->count() > 1;
    }

    protected function resolveGroupMonthlyTotal(Collection $memberships, ?float $candidateMonthlyFee = null): float
    {
        $groupMaximumFee = (float) $memberships->max(fn (Membership $membership) => $this->resolveMembershipMonthlyFeeTotal($membership));

        return round(max($groupMaximumFee, (float) ($candidateMonthlyFee ?? 0)), 2);
    }

    /**
     * Garantiza que, para el periodo dado, cada membresía del grupo (mismo
     * account_group_id, con membresía activa en 2 o más parques) tenga
     * cargada exactamente su parte proporcional del total del grupo — ni más
     * ni menos —, sin importar si alguna ya tenía un cargo previo para ese
     * periodo (p. ej. porque venía de antes de tener el segundo parque, o de
     * una corrida anterior de este mismo proceso). A cada membresía a la que
     * le falte cobrar se le crea un cargo (o complemento) solo por la
     * diferencia contra SU PROPIO cargo existente; a la que ya tenga cubierta
     * su parte no se le toca nada. Esto evita que, cuando una membresía del
     * grupo ya tenía cargo y otra no, la que faltaba terminara absorbiendo el
     * total completo del grupo en vez de solo su mitad.
     *
     * Devuelve, por cada membresía del grupo, si se le generó cargo y por
     * cuánto — lo usa el comando de generación mensual para su reporte.
     *
     * @return Collection<int, array{membership: Membership, created: bool, amount: float}>
     */
    public function ensureSplitMonthlyChargesForGroup(
        Collection $groupMemberships,
        float $groupTotalMonthlyFee,
        Carbon $chargeDate,
        ?ChargeConcept $concept = null,
        array $metadata = [],
        bool $dryRun = false
    ): Collection {
        $concept ??= $this->resolveConcept('MONTHLY_FEE');
        $membershipCount = $groupMemberships->count();
        $results = collect();

        if ($membershipCount === 0 || $groupTotalMonthlyFee <= 0) {
            return $results;
        }

        $splitAmount = round($groupTotalMonthlyFee / $membershipCount, 2);
        $allocated = 0.0;
        $lastMembership = $groupMemberships->last();

        foreach ($groupMemberships as $groupMembership) {
            $targetShare = $groupMembership->is($lastMembership)
                ? round($groupTotalMonthlyFee - $allocated, 2)
                : $splitAmount;
            $allocated = round($allocated + $targetShare, 2);

            // Mismo control que createRecurringMonthlyCharge — ver ahí.
            $backfillFloor = $this->resolveBackfillFloorMonth($groupMembership);
            $skipByFloor = $backfillFloor && $chargeDate->lt($backfillFloor);
            $skipByCancelledPeriod = $this->periodFallsWithin($chargeDate, $this->resolveCancelledPeriods($groupMembership));

            if ($skipByFloor || $skipByCancelledPeriod) {
                $results->push(['membership' => $groupMembership, 'created' => false, 'amount' => 0.0]);
                continue;
            }

            $effectiveTargetShare = $this->resolveAbsenceAdjustedMonthlyFee(
                membership: $groupMembership,
                monthlyFee: $targetShare,
                chargeDate: $chargeDate
            );

            $alreadyChargedToThisMembership = (float) Charge::query()
                ->where('membership_id', $groupMembership->id)
                ->where('concept_id', $concept->id)
                ->where('period_year', (int) $chargeDate->format('Y'))
                ->where('period_month', (int) $chargeDate->format('m'))
                ->where('status', '!=', 'cancelled')
                ->sum('amount');

            $amountToCharge = round($effectiveTargetShare - $alreadyChargedToThisMembership, 2);

            if ($amountToCharge <= 0) {
                $results->push(['membership' => $groupMembership, 'created' => false, 'amount' => 0.0]);
                continue;
            }

            if (!$dryRun) {
                $this->storeMonthlyCharge(
                    membership: $groupMembership,
                    concept: $concept,
                    chargeDate: $chargeDate,
                    amount: $amountToCharge,
                    targetMonthlyFee: $targetShare,
                    effectiveMonthlyFee: $effectiveTargetShare,
                    description: $alreadyChargedToThisMembership > 0
                        ? $this->buildMonthlyAdjustmentChargeDescription($groupMembership, $chargeDate, $effectiveTargetShare)
                        : $this->buildMonthlyChargeDescription($groupMembership, $chargeDate),
                    metadata: array_merge($metadata, [
                        'split_mode' => 'equal_group_split',
                        'split_group_total' => $groupTotalMonthlyFee,
                        'split_group_memberships' => $membershipCount,
                        'is_monthly_adjustment' => $alreadyChargedToThisMembership > 0,
                    ]),
                    dueDate: $chargeDate
                );
            }

            $results->push(['membership' => $groupMembership, 'created' => true, 'amount' => $amountToCharge]);
        }

        return $results;
    }

    protected function resolveMembershipMonthlyFeeTotal(Membership $membership, ?float $fallback = null, ?int $year = null): float
    {
        $live = $membership->resolveLiveMonthlyFee($year);

        return round((float) ($live ?? $membership->monthly_fee_total ?? $membership->monthly_fee ?? $fallback ?? 0), 2);
    }

    /**
     * Cuota mensual a cobrar por ESTA membresía: la cuota vigente del año en
     * curso según memberships.pricing_rule_fee_history. Si el socio pertenece
     * a más de un parque (billing_split_mode = equal_split, ver
     * resolveGroupPrimaryMemberships), se toma esa cuota UNA sola vez — la de
     * la regla de precio marcada para socios de varios parques
     * (pricing_rule.requires_multiple_clubs), que es la única fuente
     * confiable del monto conjunto cuando cada parque tiene su propia regla —
     * y se reparte entre los parques. Si no pertenece a más de un parque, se
     * usa tal cual, sin ningún otro ajuste.
     */
    protected function resolveMembershipMonthlyFeeShare(
        Membership $membership,
        ?float $fallback = null,
        ?int $year = null,
        ?Carbon $referenceDate = null
    ): float {
        if ($membership->billing_split_mode === 'equal_split') {
            $chargeDate = $referenceDate ?? ($year ? Carbon::create($year, 6, 15) : now());
            $groupMemberships = $this->resolveGroupPrimaryMemberships($membership, $chargeDate);

            if ($this->shouldSplitMonthlyChargesAcrossGroup($groupMemberships, 'equal_split')) {
                $fee = $this->resolveInterclubMonthlyFee($groupMemberships, $year)
                    ?? $this->resolveMembershipOwnMonthlyFee($membership, $fallback, $year);

                return round($fee / $groupMemberships->count(), 2);
            }
        }

        return $this->resolveMembershipOwnMonthlyFee($membership, $fallback, $year);
    }

    protected function resolveMembershipOwnMonthlyFee(Membership $membership, ?float $fallback, ?int $year): float
    {
        $live = $membership->resolveLiveMonthlyFee($year);

        return round((float) ($live ?? $membership->monthly_fee_share ?? $fallback ?? $membership->monthly_fee ?? 0), 2);
    }

    /**
     * La cuota mensual "combinada" del grupo: la de la membresía cuya regla
     * de precio está marcada explícitamente para socios de varios parques
     * (pricing_rule.requires_multiple_clubs). Null si ninguna del grupo tiene
     * esa marca (caso no esperado; el llamador cae de vuelta a la cuota
     * propia de la membresía).
     */
    protected function resolveInterclubMonthlyFee(Collection $groupMemberships, ?int $year): ?float
    {
        $anchor = $groupMemberships->first(fn (Membership $m) => (bool) $m->pricingRule?->requires_multiple_clubs);

        return $anchor?->resolveLiveMonthlyFee($year);
    }

    protected function resolveAbsenceAdjustedMonthlyFee(
        Membership $membership,
        float $monthlyFee,
        Carbon $chargeDate
    ): float {
        $absencePermit = $this->resolveApplicableAbsencePermit($membership, $chargeDate);

        if (!$absencePermit) {
            return $monthlyFee;
        }

        return round($monthlyFee * ((float) $absencePermit->charge_percentage / 100), 2);
    }

    /**
     * Por account_group_id cuando la cuenta pertenece a un grupo — así
     * aplica también a las cuentas hermanas del socio en otros parques
     * (p. ej. un socio con cuenta en PE1 y PE2, ver
     * MemberController::storeAbsencePermit). Si la cuenta no tiene grupo
     * (frecuente en datos migrados), se resuelve por membership_account_id
     * directo — aplica solo a esta cuenta, que es lo correcto para un socio
     * de un solo parque.
     */
    protected function resolveApplicableAbsencePermit(
        Membership $membership,
        Carbon $chargeDate
    ): ?AbsencePermit {
        $accountGroupId = $membership->account?->account_group_id;
        $accountId = $membership->membership_account_id;

        if (!$accountGroupId && !$accountId) {
            return null;
        }

        return AbsencePermit::query()
            ->where(function ($scope) use ($accountGroupId, $accountId) {
                if ($accountGroupId) {
                    $scope->where('account_group_id', $accountGroupId);
                }
                if ($accountId) {
                    $scope->orWhere('membership_account_id', $accountId);
                }
            })
            ->whereIn('status', ['approved', 'active'])
            ->whereDate('start_date', '<=', $chargeDate->toDateString())
            ->whereDate('end_date', '>=', $chargeDate->toDateString())
            ->orderBy('start_date')
            ->first();
    }
}
