<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('feedback.categories')->insert([
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
        ]);
    }
}
