<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations.status')->insert([
            'id'         => 5,
            'name'       => 'ASISTENCIA',
            'color'      => 'green',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('reservations.status')->where('id', 5)->delete();
    }
};
