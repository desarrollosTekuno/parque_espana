<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => 'INSTALACIONES', 'code' => 'facilities'],
            ['name' => 'SERVICIO', 'code' => 'service'],
            ['name' => 'AMENIDADES', 'code' => 'amenities'],
            ['name' => 'ACCESO', 'code' => 'access'],
            ['name' => 'PAGOS', 'code' => 'payments'],
            ['name' => 'EVENTOS', 'code' => 'events'],
            ['name' => 'INVITADOS', 'code' => 'guests'],
            ['name' => 'LOCKERS', 'code' => 'lockers'],
            ['name' => 'APLICACIÓN', 'code' => 'app'],
            ['name' => 'SUGERENCIA GENERAL', 'code' => 'general_suggestion'],
            ['name' => 'CAFETERÍA', 'code' => 'cafeteria'],
            ['name' => 'LIMPIEZA', 'code' => 'cleanliness'],
        ];

        foreach ($data as $item) {
            DB::table('feedback.categories')->updateOrInsert(
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
