<?php

namespace App\Services\Billing;

use App\Models\Memberships\AbsencePermit;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MembershipChargeService
{
    public function synchronizeMembershipFees(
        Membership $membership,
        ?float $groupTotalMonthlyFee = null,
        ?Carbon $effectiveDate = null,
        ?string $billingSplitMode = null,
        ?string $historyReason = null
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

                $groupMembership->update([
                    'monthly_fee' => $groupTotal,
                    'monthly_fee_total' => $groupTotal,
                    'monthly_fee_share' => $share,
                    'billing_split_mode' => 'equal_split',
                    'is_billable' => $share > 0,
                ]);

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

            $groupMembership->update([
                'monthly_fee' => $total,
                'monthly_fee_total' => $total,
                'monthly_fee_share' => $total,
                'billing_split_mode' => 'single',
                'is_billable' => $total > 0,
            ]);

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

        if ($this->hasMonthlyChargeForPeriod($membership, $chargeDate, $monthlyConcept->id)) {
            return false;
        }

        $monthlyFee = round((float) ($monthlyFeeOverride ?? $this->resolveMembershipMonthlyFeeShare($membership)), 2);
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
                'monthly_fee_total' => $this->resolveMembershipMonthlyFeeTotal($membership),
                'monthly_fee_share' => $monthlyFee,
                'effective_monthly_fee' => $effectiveMonthlyFee,
                'generation_type' => 'monthly_cycle',
                'generated_at' => now()->toDateTimeString(),
            ]),
        ]);

        return true;
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

    public function createInitialCharges(
        Membership $membership,
        float $monthlyFee,
        float $inscriptionFee = 0,
        array $metadata = [],
        ?Carbon $chargeDate = null,
        bool $reconcileExistingMonthlyCharge = false
    ): void {
        $chargeDate = ($chargeDate ?? now())->copy()->startOfDay();
        $monthlyConcept = $this->resolveConcept('MONTHLY_FEE');
        $groupMemberships = $this->resolveGroupPrimaryMemberships($membership, $chargeDate);
        $splitAcrossGroup = $this->shouldSplitMonthlyChargesAcrossGroup($groupMemberships);
        $groupTotalMonthlyFee = $splitAcrossGroup
            ? $this->resolveGroupMonthlyTotal(
                memberships: $groupMemberships,
                candidateMonthlyFee: $monthlyFee
            )
            : null;
        $shouldReconcileAgainstExistingCharges = $reconcileExistingMonthlyCharge || $splitAcrossGroup;
        $existingPeriodMonthlyAmount = $shouldReconcileAgainstExistingCharges
            ? $this->resolveExistingPeriodMonthlyAmount(
                membership: $membership,
                conceptId: $monthlyConcept->id,
                chargeDate: $chargeDate
            )
            : 0.0;

        if ($splitAcrossGroup && $existingPeriodMonthlyAmount <= 0) {
            $this->createSplitInitialMonthlyCharges(
                memberships: $groupMemberships,
                totalMonthlyFee: (float) $groupTotalMonthlyFee,
                chargeDate: $chargeDate,
                metadata: $metadata,
                concept: $monthlyConcept
            );
        } else {
            $effectiveMonthlyFee = $this->resolveAbsenceAdjustedMonthlyFee(
                membership: $membership,
                monthlyFee: $splitAcrossGroup
                    ? (float) $groupTotalMonthlyFee
                    : $this->resolveMembershipMonthlyFeeShare($membership, $monthlyFee),
                chargeDate: $chargeDate
            );

            if (((bool) $membership->is_billable || $shouldReconcileAgainstExistingCharges) && $effectiveMonthlyFee > 0) {
                $monthlyChargeAmount = $effectiveMonthlyFee;
                $monthlyChargeDescription = $this->buildMonthlyChargeDescription($membership, $chargeDate);

                if ($shouldReconcileAgainstExistingCharges) {
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
                        targetMonthlyFee: $splitAcrossGroup
                            ? (float) $groupTotalMonthlyFee
                            : $this->resolveMembershipMonthlyFeeShare($membership, $monthlyFee),
                        effectiveMonthlyFee: $effectiveMonthlyFee,
                        description: $monthlyChargeDescription,
                        metadata: array_merge($metadata, [
                            'is_monthly_adjustment' => $shouldReconcileAgainstExistingCharges,
                            'split_mode' => $splitAcrossGroup ? 'equal_group_split_adjustment' : null,
                            'split_group_total' => $groupTotalMonthlyFee,
                            'split_group_memberships' => $splitAcrossGroup ? $groupMemberships->count() : null,
                        ]),
                        dueDate: $chargeDate
                    );
                }
            }
        }

        if ($inscriptionFee > 0) {
            $inscriptionConcept = $this->resolveConcept('INSCRIPTION');

            Charge::create([
                'membership_account_id' => $membership->membership_account_id,
                'membership_id' => $membership->id,
                'member_id' => $membership->account?->primaryHolder?->member_id,
                'concept_id' => $inscriptionConcept->id,
                'description' => $this->buildInscriptionChargeDescription($membership),
                'amount' => $inscriptionFee,
                'balance' => $inscriptionFee,
                'issue_date' => $chargeDate->toDateString(),
                'due_date' => $chargeDate->toDateString(),
                'period_year' => null,
                'period_month' => null,
                'allows_partial_payments' => (bool) $inscriptionConcept->allows_partial_payments,
                'status' => 'pending',
                'metadata' => array_merge($metadata, [
                    'concept_code' => $inscriptionConcept->code,
                ]),
            ]);
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
                'monthly_fee_total' => $this->resolveMembershipMonthlyFeeTotal($membership),
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

    protected function buildInscriptionChargeDescription(Membership $membership): string
    {
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresía';

        return sprintf('Inscripción - %s', $membershipTypeName);
    }

    protected function resolveExistingPeriodMonthlyAmount(
        Membership $membership,
        int $conceptId,
        Carbon $chargeDate
    ): float {
        $accountIds = MembershipAccount::query()
            ->when(
                $membership->account?->account_group_id,
                fn ($query) => $query->where('account_group_id', $membership->account->account_group_id),
                fn ($query) => $query->where('id', $membership->membership_account_id)
            )
            ->pluck('id');

        return (float) Charge::query()
            ->whereIn('membership_account_id', $accountIds)
            ->where('concept_id', $conceptId)
            ->where('period_year', (int) $chargeDate->format('Y'))
            ->where('period_month', (int) $chargeDate->format('m'))
            ->where('status', '!=', 'cancelled')
            ->sum('amount');
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

    protected function createSplitInitialMonthlyCharges(
        Collection $memberships,
        float $totalMonthlyFee,
        Carbon $chargeDate,
        array $metadata,
        ChargeConcept $concept
    ): void {
        $membershipCount = $memberships->count();

        if ($membershipCount === 0 || $totalMonthlyFee <= 0) {
            return;
        }

        $splitAmount = round($totalMonthlyFee / $membershipCount, 2);
        $allocated = 0.0;
        $lastMembership = $memberships->last();

        foreach ($memberships as $groupMembership) {
            if ($this->hasMonthlyChargeForPeriod($groupMembership, $chargeDate, $concept->id)) {
                continue;
            }

            $targetMonthlyFee = $groupMembership->is($lastMembership)
                ? round($totalMonthlyFee - $allocated, 2)
                : $splitAmount;

            $allocated = round($allocated + $targetMonthlyFee, 2);

            $effectiveMonthlyFee = $this->resolveAbsenceAdjustedMonthlyFee(
                membership: $groupMembership,
                monthlyFee: $targetMonthlyFee,
                chargeDate: $chargeDate
            );

            if ($effectiveMonthlyFee <= 0) {
                continue;
            }

            $this->storeMonthlyCharge(
                membership: $groupMembership,
                concept: $concept,
                chargeDate: $chargeDate,
                amount: $effectiveMonthlyFee,
                targetMonthlyFee: $targetMonthlyFee,
                effectiveMonthlyFee: $effectiveMonthlyFee,
                description: $this->buildMonthlyChargeDescription($groupMembership, $chargeDate),
                metadata: array_merge($metadata, [
                    'split_mode' => 'equal_group_split',
                    'split_group_total' => $totalMonthlyFee,
                    'split_group_memberships' => $membershipCount,
                    'is_monthly_adjustment' => false,
                ]),
                dueDate: $chargeDate
            );
        }
    }

    protected function resolveMembershipMonthlyFeeTotal(Membership $membership, ?float $fallback = null): float
    {
        return round((float) ($membership->monthly_fee_total ?? $membership->monthly_fee ?? $fallback ?? 0), 2);
    }

    protected function resolveMembershipMonthlyFeeShare(Membership $membership, ?float $fallback = null): float
    {
        return round((float) ($membership->monthly_fee_share ?? $fallback ?? $membership->monthly_fee ?? 0), 2);
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

    protected function resolveApplicableAbsencePermit(
        Membership $membership,
        Carbon $chargeDate
    ): ?AbsencePermit {
        $accountGroupId = $membership->account?->account_group_id;

        if (!$accountGroupId) {
            return null;
        }

        return AbsencePermit::query()
            ->where('account_group_id', $accountGroupId)
            ->whereIn('status', ['approved', 'active'])
            ->whereDate('start_date', '<=', $chargeDate->toDateString())
            ->whereDate('end_date', '>=', $chargeDate->toDateString())
            ->orderBy('start_date')
            ->first();
    }
}
