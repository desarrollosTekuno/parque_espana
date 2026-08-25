<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.coach_specialties', function (Blueprint $table) {
            $table->foreignId('coach_id')
                ->constrained('classes.coaches')
                ->cascadeOnDelete();

            $table->foreignId('specialty_id')
                ->constrained('classes.specialties')
                ->cascadeOnDelete();

            $table->primary(['coach_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.coach_specialties');
    }
};
