<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.coach_availabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coach_id')
                ->constrained('classes.coaches')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week'); // 0=Domingo ... 6=Sabado
            $table->time('start_time');
            $table->time('end_time');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.coach_availabilities');
    }
};
