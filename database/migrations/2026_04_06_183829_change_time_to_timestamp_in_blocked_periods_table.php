<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE amenities.blocked_periods ALTER COLUMN start_time TYPE timestamp USING ('1970-01-01 ' || start_time)::timestamp");  
        DB::statement("ALTER TABLE amenities.blocked_periods ALTER COLUMN end_time TYPE timestamp USING ('1970-01-01 ' || end_time)::timestamp");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE amenities.blocked_periods ALTER COLUMN start_time TYPE time USING start_time::time");
        DB::statement("ALTER TABLE amenities.blocked_periods ALTER COLUMN end_time TYPE time USING end_time::time");
    }
};
