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
        DB::statement('ALTER TABLE amenity_resources ALTER COLUMN capacity DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('UPDATE amenity_resources SET capacity = 1 WHERE capacity IS NULL');
        DB::statement('ALTER TABLE amenity_resources ALTER COLUMN capacity SET NOT NULL');
    }
};
