<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackPrioritiesSeeder extends Seeder {
    public function run(): void
    {
        $data = [
            ['name' => 'BAJA', 'code' => 'low', 'sort_order' => 1],
            ['name' => 'MEDIA', 'code' => 'medium', 'sort_order' => 2],
            ['name' => 'ALTA', 'code' => 'high', 'sort_order' => 3],
            ['name' => 'URGENTE', 'code' => 'urgent', 'sort_order' => 4],
        ];

        foreach ($data as $item) {
            DB::table('feedback.priorities')->updateOrInsert(
                ['code' => strtoupper($item['code'])],
                [
                    'name' => strtoupper($item['name']),
                    'code' => strtoupper($item['code']),
                    'sort_order' => $item['sort_order'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
