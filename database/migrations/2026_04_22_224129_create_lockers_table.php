<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('members.lockers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs.clubs');

            $table->integer('number');
            $table->enum('category', ['ninos', 'ninas', 'caballeros', 'damas']);

            $table->enum('status', ['disponible', 'ocupado', 'mantenimiento', 'pago_pendiente'])
                ->default('disponible');

            $table->timestamps();

            $table->unique(['club_id', 'number', 'category']);

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.lockers');
    }
};
