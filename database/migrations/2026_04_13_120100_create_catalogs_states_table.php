<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalogs.states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id');
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('name');
            $table->string('iso2', 10)->nullable();
            $table->string('type')->nullable();
            $table->decimal('latitude', 12, 8)->nullable();
            $table->decimal('longitude', 12, 8)->nullable();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('catalogs.countries')->cascadeOnDelete();
            $table->index('external_id');
            $table->index(['country_id', 'iso2']);
            $table->unique(['country_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogs.states');
    }
};
