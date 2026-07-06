<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.class_schedules', function (Blueprint $table) {
            $table->id();

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

            $table->string('name');
            $table->string('type'); // adults | kids
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday ... 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.class_schedules');
    }
};
