<?php

namespace App\Services\Billing;

use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MembershipPricingService
{
    /**
     * Recalculate fees for every active primary membership that shares an account
     * group with the cancelled account. Called after an account cancellation so
     * group mates revert from the interclub/split rate to their standalone price.
     */
    public function recalculateGroupFeesAfterCancellation(
        MembershipAccount $cancelledAccount,
        MembershipChargeService $chargeService
    ): void {
        $accountGroupId = $cancelledAccount->account_group_id;

        if (!$accountGroupId) {
            return;
        }

        $remainingMemberships = Membership::query()
            ->with(['membershipType', 'account.primaryHolder.member'])
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('account', fn (Builder $q) => $q->where('account_group_id', $accountGroupId))
            ->get();

        if ($remainingMemberships->isEmpty()) {
            return;
        }

        // After cancellation the group no longer has multiple clubs active,
        // so we look up the standalone pricing rule for each remaining membership.
        foreach ($remainingMemberships as $membership) {
            $membershipType = $membership->membershipType;

            if (!$membershipType) {
                continue;
            }

            $primaryMember = $membership->account?->primaryHolder?->member;
            $age = $this->shouldApplyAgeFilter($membershipType) ? $primaryMember?->age : null;

            $pricingRule = $this->resolvePricingRule(
                membershipTypeId: $membership->membership_type_id,
                fromMembershipTypeId: $membership->origin_membership_type_id,
                age: $age,
                hasMultipleClubs: false
            );

            if (!$pricingRule) {
                Log::warning('No se encontró pricing rule standalone para membresía tras cancelación de grupo', [
                    'membership_id' => $membership->id,
                    'membership_type_id' => $membership->membership_type_id,
                ]);
                continue;
            }

            $standaloneFee = (float) $pricingRule->monthly_fee;

            $chargeService->synchronizeMembershipFees(
                membership: $membership,
                groupTotalMonthlyFee: $standaloneFee,
                effectiveDate: null,
                billingSplitMode: 'single',
                historyReason: 'Recálculo de cuota: baja de cuenta en grupo'
            );
        }
    }

    /**
     * Recalculate fees for all active primary memberships in the same account
     * group as the reactivated membership. Called after a reactivation so the
     * group fee split is re-established (e.g. interclub package rate).
     */
    public function recalculateGroupFeesAfterReactivation(
        Membership $reactivatedMembership,
        MembershipChargeService $chargeService
    ): void {
        // synchronizeMembershipFees already resolves the full group context:
        // it finds all active memberships in the group, picks the max total fee,
        // and re-applies the split mode stored on the reactivated membership.
        $chargeService->synchronizeMembershipFees(
            membership: $reactivatedMembership,
            historyReason: 'Recálculo de cuota: reactivación de cuenta en grupo'
        );
    }

    public function resolvePricingRule(
        int $membershipTypeId,
        ?int $fromMembershipTypeId,
        ?int $age,
        bool $hasMultipleClubs
    ): ?PricingRule {
        $attempts = [];

        // When interclub applies, exhaust ALL interclub rules before falling back
        // to standalone. Without this, a standalone from-type rule (e.g. familiar→individual
        // standalone) would be found before the generic interclub rule, producing the wrong fee.
        if ($hasMultipleClubs) {
            if ($fromMembershipTypeId) {
                $attempts[] = [$fromMembershipTypeId, true];
            }
            $attempts[] = [null, true];
        }

        if ($fromMembershipTypeId) {
            $attempts[] = [$fromMembershipTypeId, false];
        }

        $attempts[] = [null, false];

        foreach ($attempts as [$candidateFromMembershipTypeId, $requiresMultipleClubs]) {
            $rule = $this->buildPricingRuleQuery(
                membershipTypeId: $membershipTypeId,
                fromMembershipTypeId: $candidateFromMembershipTypeId,
                age: $age,
                requiresMultipleClubs: $requiresMultipleClubs
            )
                ->orderBy('priority')
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        return null;
    }

    public function buildPricingRuleQuery(
        int $membershipTypeId,
        ?int $fromMembershipTypeId,
        ?int $age,
        bool $requiresMultipleClubs
    ): Builder {
        return PricingRule::query()
            ->where('membership_type_id', $membershipTypeId)
            ->where('is_active', true)
            ->when(
                $fromMembershipTypeId !== null,
                fn (Builder $query) => $query->where('from_membership_type_id', $fromMembershipTypeId),
                fn (Builder $query) => $query->whereNull('from_membership_type_id')
            )
            ->when(
                $age !== null,
                function (Builder $query) use ($age) {
                    $query->where(function (Builder $ageQuery) use ($age) {
                        $ageQuery->whereNull('min_age')->orWhere('min_age', '<=', $age);
                    })->where(function (Builder $ageQuery) use ($age) {
                        $ageQuery->whereNull('max_age')->orWhere('max_age', '>=', $age);
                    });
                },
                fn (Builder $query) => $query->whereNull('min_age')->whereNull('max_age')
            )
            ->where('requires_multiple_clubs', $requiresMultipleClubs)
            ->where(function (Builder $query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now()->toDateString());
            })
            ->where(function (Builder $query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now()->toDateString());
            });
    }

    public function shouldApplyAgeFilter(MembershipType $membershipType): bool
    {
        return Str::contains((string) $membershipType->code, '_SOL');
    }
}
