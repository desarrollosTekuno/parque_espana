<?php

namespace App\Services\Billing;

use App\Models\Memberships\AbsencePermit;
use App\Models\Billing\Charge;
use App\Models\Billing\ChargeConcept;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use Carbon\Carbon;

class MembershipChargeService
{
    public function createInitialCharges(
        Membership $membership,
        float $monthlyFee,
        float $inscriptionFee = 0,
        array $metadata = [],
        ?Carbon $chargeDate = null,
        bool $reconcileExistingMonthlyCharge = false
    ): void {
        $chargeDate = ($chargeDate ?? now())->copy()->startOfDay();
        $effectiveMonthlyFee = $this->resolveAbsenceAdjustedMonthlyFee(
            membership: $membership,
            monthlyFee: $monthlyFee,
            chargeDate: $chargeDate
        );

        if ((bool) $membership->is_billable && $effectiveMonthlyFee > 0) {
            $monthlyConcept = $this->resolveConcept('MONTHLY_FEE');
            $monthlyChargeAmount = $effectiveMonthlyFee;
            $monthlyChargeDescription = $this->buildMonthlyChargeDescription($membership, $chargeDate);

            if ($reconcileExistingMonthlyCharge) {
                $existingPeriodMonthlyAmount = $this->resolveExistingPeriodMonthlyAmount(
                    membership: $membership,
                    conceptId: $monthlyConcept->id,
                    chargeDate: $chargeDate
                );

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
                Charge::create([
                    'membership_account_id' => $membership->membership_account_id,
                    'membership_id' => $membership->id,
                    'member_id' => $membership->account?->primaryHolder?->member_id,
                    'concept_id' => $monthlyConcept->id,
                    'description' => $monthlyChargeDescription,
                    'amount' => $monthlyChargeAmount,
                    'balance' => $monthlyChargeAmount,
                    'issue_date' => $chargeDate->toDateString(),
                    'due_date' => $chargeDate->toDateString(),
                    'period_year' => (int) $chargeDate->format('Y'),
                    'period_month' => (int) $chargeDate->format('m'),
                    'allows_partial_payments' => (bool) $monthlyConcept->allows_partial_payments,
                    'status' => 'pending',
                    'metadata' => array_merge($metadata, [
                        'concept_code' => $monthlyConcept->code,
                        'target_monthly_fee' => $monthlyFee,
                        'effective_monthly_fee' => $effectiveMonthlyFee,
                        'is_monthly_adjustment' => $reconcileExistingMonthlyCharge,
                    ]),
                ]);
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

    protected function buildMonthlyChargeDescription(Membership $membership, Carbon $chargeDate): string
    {
        $monthLabel = $chargeDate->locale('es')->translatedFormat('F Y');
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresia';

        return sprintf('Mensualidad %s - %s', ucfirst($monthLabel), $membershipTypeName);
    }

    protected function buildMonthlyAdjustmentChargeDescription(
        Membership $membership,
        Carbon $chargeDate,
        float $totalMonthlyFee
    ): string {
        $monthLabel = $chargeDate->locale('es')->translatedFormat('F Y');
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresia';

        return sprintf(
            'Complemento de mensualidad %s - %s (total del periodo $%s)',
            ucfirst($monthLabel),
            $membershipTypeName,
            number_format($totalMonthlyFee, 2)
        );
    }

    protected function buildInscriptionChargeDescription(Membership $membership): string
    {
        $membershipTypeName = $membership->membershipType?->name ?? 'Membresia';

        return sprintf('Inscripcion - %s', $membershipTypeName);
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
