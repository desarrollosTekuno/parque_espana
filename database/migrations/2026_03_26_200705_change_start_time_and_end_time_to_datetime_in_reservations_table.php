<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE reservations
            ALTER COLUMN start_time TYPE timestamp
            USING (CURRENT_DATE + start_time)
        ");

        DB::statement("
            ALTER TABLE reservations
            ALTER COLUMN end_time TYPE timestamp
            USING (CURRENT_DATE + end_time)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE reservations
            ALTER COLUMN start_time TYPE time
            USING start_time::time
        ");

        DB::statement("
            ALTER TABLE reservations
            ALTER COLUMN end_time TYPE time
            USING end_time::time
        ");
    }
};