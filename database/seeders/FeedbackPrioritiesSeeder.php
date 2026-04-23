<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackPrioritiesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('feedback.priorities')->insert([
            ['name' => 'BAJA', 'code' => 'low', 'sort_order' => 1],
            ['name' => 'MEDIA', 'code' => 'medium', 'sort_order' => 2],
            ['name' => 'ALTA', 'code' => 'high', 'sort_order' => 3],
            ['name' => 'URGENTE', 'code' => 'urgent', 'sort_order' => 4],
        ]);
    }
}
