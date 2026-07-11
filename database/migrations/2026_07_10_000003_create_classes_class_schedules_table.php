<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.class_schedules', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('type'); // adults | kids
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday ... 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->date('start_date')->nullable(); // null = vigente desde su creación
            $table->date('end_date')->nullable();   // null = permanente
            $table->unsignedInteger('capacity');
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('club_id');
            $table->foreign('club_id')
                ->references('id')
                ->on('clubs.clubs')
                ->cascadeOnDelete();

            $table->foreignId('coach_id')
                ->constrained('classes.coaches')
                ->cascadeOnDelete();

            $table->foreignId('amenity_resource_id')
                ->constrained('amenities.resources')
                ->cascadeOnDelete();

            $table->foreignId('specialty_id')
                ->constrained('classes.specialties')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.class_schedules');
    }
};
