<?php

namespace Database\Factories\Memberships;

use App\Models\Administrator\Club;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'membership_account_id' => MembershipAccount::factory(),
            'club_id' => Club::query()->value('id'),
            'membership_type_id' => MembershipType::query()->value('id'),
            'origin_membership_type_id' => null,
            'is_primary' => true,
            'is_billable' => true,
            'monthly_fee' => 1000,
            'monthly_fee_total' => 1000,
            'monthly_fee_share' => 1000,
            'billing_split_mode' => 'single',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => null,
            'status' => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => null,
        ]);
    }
}
