<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

        Schema::create('catalogs.countries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('name');
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->nullable();
            $table->string('numeric_code', 10)->nullable();
            $table->string('phonecode', 10)->nullable();
            $table->string('capital')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol', 20)->nullable();
            $table->string('native')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->string('nationality')->nullable();
            $table->string('emoji', 20)->nullable();
            $table->timestamps();

            $table->index('external_id');
            $table->index('iso3');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs.countries');
    }
};
