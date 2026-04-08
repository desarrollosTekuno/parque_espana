<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members.addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            $table->string('street')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable()->default('México');

            // tiempo de radicar en esta ciudad
            $table->unsignedSmallInteger('years_in_city')->nullable();

            // por si luego quieres manejar varios domicilios
            $table->boolean('is_primary')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members.addresses');
    }
};