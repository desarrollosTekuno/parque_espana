<?php

namespace Database\Seeders;

use App\Models\Members\Member;
use Illuminate\Database\Seeder;

class MemberWithUserSeeder extends Seeder
{
    public function run(): void
    {
        Member::factory()
            ->count(50)
            ->individualWithUserAndMembership()
            ->create();
    }
}
