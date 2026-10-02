<?php

namespace App\Services\Billing;

use App\Models\Billing\Charge;
use App\Models\Memberships\MembershipAccount;
use Illuminate\Validation\ValidationException;

class MaterialDamagePaymentGuard {
    public function ensureCanPay(int $accountId, array $applications = [], bool $includesOtherConcepts = false): void {
        $pendingDamageCharges = Charge::query()
            ->whereIn('membership_account_id', $this->relatedAccountIds($accountId))
            ->whereHas('concept', fn ($query) => $query->withTrashed()->where('code', 'CD'))
            ->whereIn('status', ['pending', 'partial'])
            ->where('balance', '>', 0)
            ->get()
            ->keyBy('id');

        if ($pendingDamageCharges->isEmpty()) {
            return;
        }

        if ($includesOtherConcepts || empty($applications)) {
            throw ValidationException::withMessages([
                'applications' => 'Primero debes liquidar el cargo por daños materiales en un cobro separado.',
            ]);
        }

        foreach ($applications as $application) {
            $charge = $pendingDamageCharges->get((int) ($application['charge_id'] ?? 0));
            $amount = round((float) ($application['amount'] ?? 0), 2);

            if (!$charge || $amount !== round((float) $charge->balance, 2)) {
                throw ValidationException::withMessages([
                    'applications' => 'Primero debes liquidar el cargo por daños materiales en un cobro separado.',
                ]);
            }
        }
    }

    /** Cuentas del usuario en ambos parques (mismo account_group_id). */
    public function relatedAccountIds(int $accountId): array {
        $groupId = MembershipAccount::query()->whereKey($accountId)->value('account_group_id');

        if (!$groupId) {
            return [$accountId];
        }

        return MembershipAccount::query()->where('account_group_id', $groupId)->pluck('id')->all();
    }
}
