<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackStatusesSeeder extends Seeder {
    public function run(): void
    {
        $data = [
            ['name' => 'ENVIADO', 'code' => 'submitted', 'color' => '#6B7280', 'sort_order' => 1],
            ['name' => 'EN REVISIÓN', 'code' => 'under_review', 'color' => '#F59E0B', 'sort_order' => 2],
            ['name' => 'EN PROCESO', 'code' => 'in_progress', 'color' => '#3B82F6', 'sort_order' => 3],
            ['name' => 'RESUELTO', 'code' => 'resolved', 'color' => '#10B981', 'sort_order' => 4],
            ['name' => 'RECHAZADO', 'code' => 'rejected', 'color' => '#EF4444', 'sort_order' => 5],
            ['name' => 'CERRADO', 'code' => 'closed', 'color' => '#111827', 'sort_order' => 6],
        ];

        foreach ($data as $item) {
            DB::table('feedback.statuses')->updateOrInsert(
                ['code' => strtoupper($item['code'])],
                [
                    'name' => strtoupper($item['name']),
                    'code' => strtoupper($item['code']),
                    'color' => $item['color'],
                    'sort_order' => $item['sort_order'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
