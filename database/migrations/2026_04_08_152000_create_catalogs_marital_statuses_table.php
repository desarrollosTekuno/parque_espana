<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS catalogs');
        } else {
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'catalogs')
                BEGIN
                    EXEC('CREATE SCHEMA catalogs');
                END
            ");
        }

        Schema::create('catalogs.marital_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs.marital_statuses');
    }
};
