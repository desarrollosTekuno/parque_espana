<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackTicketTypesSeeder extends Seeder {
    public function run(): void {
        $data = [
            ['name' => 'QUEJA', 'code' => 'complaint'],
            ['name' => 'SUGERENCIA', 'code' => 'suggestion'],
        ];

        foreach ($data as $item) {
            DB::table('feedback.ticket_types')->updateOrInsert(
                ['code' => strtoupper($item['code'])],
                [
                    'name' => strtoupper($item['name']),
                    'code' => strtoupper($item['code']),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
