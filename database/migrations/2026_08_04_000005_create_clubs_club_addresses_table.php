<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS clubs');
        } else {
            DB::statement("IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'clubs') EXEC('CREATE SCHEMA clubs')");
        }

        Schema::create('clubs.club_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')
                ->unique()
                ->constrained('clubs.clubs')
                ->cascadeOnDelete();
            $table->string('street')->nullable();
            $table->string('exterior_number', 30)->nullable();
            $table->string('interior_number', 30)->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->foreignId('country_id')
                ->nullable()
                ->constrained('catalogs.countries');
            $table->foreignId('state_id')
                ->nullable()
                ->constrained('catalogs.states');
            $table->foreignId('city_id')
                ->nullable()
                ->constrained('catalogs.cities');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clubs.club_addresses');
    }
};
