<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackTicketTypesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('feedback.ticket_types')->insert([
            ['name' => 'QUEJA', 'code' => 'complaint'],
            ['name' => 'SUGERENCIA', 'code' => 'suggestion'],
        ]);
    }
}
