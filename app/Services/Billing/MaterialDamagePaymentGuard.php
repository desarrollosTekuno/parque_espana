<?php

namespace App\Services\Billing;

use App\Models\Billing\Charge;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
class MaterialDamagePaymentGuard {
    const MATERIAL_DAMAGE_CONCEPT_CODE = 'CD';

    const BLOCKED_PAYMENT_MESSAGE = 'Primero debes liquidar el cargo por daños materiales en un cobro separado.';

    public function ensureCanPay(int $accountId, array $applications = [], bool $includesOtherConcepts = false): void {
        $pendingDamageCharges = $this->getPendingDamageCharges($accountId);

        if ($pendingDamageCharges->isEmpty()) {
            return;
        }

        if ($includesOtherConcepts) {
            $this->blockPayment();
        }

        if (empty($applications)) {
            $this->blockPayment();
        }

        foreach ($applications as $application) {
            $isValid = $this->isFullPaymentOfDamageCharge($application, $pendingDamageCharges);

            if (!$isValid) {
                $this->blockPayment();
            }
        }
    }

    public function relatedAccountIds(int $accountId): array {
        $account = MembershipAccount::query()->find($accountId);

        if ($account === null || $account->account_group_id === null) {
            return [$accountId];
        }

        $accountIds = MembershipAccount::query()
            ->where('account_group_id', $account->account_group_id)
            ->pluck('id')
            ->all();

        return $accountIds;
    }

    private function getPendingDamageCharges(int $accountId): Collection {
        $accountIds = $this->relatedAccountIds($accountId);

        $charges = Charge::query()
            ->whereIn('membership_account_id', $accountIds)
            ->whereHas('concept', function ($conceptQuery) {
                $conceptQuery->withTrashed()
                    ->where('code', self::MATERIAL_DAMAGE_CONCEPT_CODE);
            })
            ->whereIn('status', ['pending', 'partial'])
            ->where('balance', '>', 0)
            ->get();

        return $charges->keyBy('id');
    }

    private function isFullPaymentOfDamageCharge(array $application, Collection $pendingDamageCharges): bool {
        $chargeId = (int) ($application['charge_id'] ?? 0);

        if (!$pendingDamageCharges->has($chargeId)) {
            return false;
        }

        $damageCharge = $pendingDamageCharges->get($chargeId);

        $amountToPay = round((float) ($application['amount'] ?? 0), 2);
        $pendingBalance = round((float) $damageCharge->balance, 2);

        if ($amountToPay !== $pendingBalance) {
            return false;
        }

        return true;
    }

    private function blockPayment(): void {
        throw ValidationException::withMessages([
            'applications' => self::BLOCKED_PAYMENT_MESSAGE,
        ]);
    }
}
