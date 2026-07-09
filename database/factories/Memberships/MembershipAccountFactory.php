<?php

namespace Database\Factories\Memberships;

use App\Models\Administrator\Club;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipAccount>
 */
class MembershipAccountFactory extends Factory
{
    protected $model = MembershipAccount::class;

    public function definition(): array
    {
        $number = 'TEST-' . $this->faker->unique()->numerify('#####');

        return [
            'account_group_id' => MembershipAccountGroup::factory(),
            'club_id' => Club::query()->value('id'),
            'membership_number' => $number,
            'internal_account_number' => $number,
            'account_type' => 'individual',
            'status' => 'active',
        ];
    }

    public function family(): static
    {
        return $this->state([
            'account_type' => 'family',
        ]);
    }

    public function individual(): static
    {
        return $this->state([
            'account_type' => 'individual',
        ]);
    }
}
