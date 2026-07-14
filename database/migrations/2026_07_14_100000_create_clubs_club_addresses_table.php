<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs.club_addresses', function (Blueprint $table) {
            $table->id();

            $table->string('street')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('postal_code', 10)->nullable();

            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('catalogs.countries');
            $table->foreignId('state_id')->nullable()->constrained('catalogs.states');
            $table->foreignId('city_id')->nullable()->constrained('catalogs.cities');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clubs.club_addresses');
    }
};
