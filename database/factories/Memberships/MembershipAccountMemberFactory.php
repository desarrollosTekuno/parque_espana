<?php

namespace Database\Factories\Memberships;

use App\Models\Catalogs\Relationship;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipAccountMember>
 */
class MembershipAccountMemberFactory extends Factory
{
    protected $model = MembershipAccountMember::class;

    public function definition(): array
    {
        return [
            'membership_account_id' => MembershipAccount::factory(),
            'member_id' => Member::factory(),
            'relationship_id' => Relationship::query()->value('id'),
            'is_primary_holder' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state([
            'is_primary_holder' => true,
        ]);
    }
}
