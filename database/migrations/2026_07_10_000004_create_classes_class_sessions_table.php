<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.class_sessions', function (Blueprint $table) {
            $table->id();

            $table->date('date');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('current_capacity')->default(0);
            $table->string('status')->default('scheduled'); // scheduled | cancelled
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedBigInteger('club_id');
            $table->foreign('club_id')
                ->references('id')
                ->on('clubs.clubs')
                ->cascadeOnDelete();

            $table->foreignId('class_schedule_id')
                ->constrained('classes.class_schedules')
                ->cascadeOnDelete();

            $table->foreignId('coach_id')
                ->constrained('classes.coaches')
                ->cascadeOnDelete();

            $table->foreignId('amenity_resource_id')
                ->constrained('amenities.resources')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['class_schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.class_sessions');
    }
};
