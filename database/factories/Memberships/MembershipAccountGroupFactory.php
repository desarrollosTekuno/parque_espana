<?php

namespace Database\Factories\Memberships;

use App\Models\Memberships\MembershipAccountGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipAccountGroup>
 */
class MembershipAccountGroupFactory extends Factory
{
    protected $model = MembershipAccountGroup::class;

    public function definition(): array
    {
        return [
            'status' => 'active',
        ];
    }
}
