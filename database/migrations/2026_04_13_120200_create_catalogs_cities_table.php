<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalogs.cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id');
            $table->foreignId('state_id')->nullable();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('name');
            $table->decimal('latitude', 12, 8)->nullable();
            $table->decimal('longitude', 12, 8)->nullable();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('catalogs.countries')->cascadeOnDelete();
            $table->foreign('state_id')->references('id')->on('catalogs.states')->nullOnDelete();
            $table->index('external_id');
            $table->unique(['country_id', 'state_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs.cities');
    }
};
