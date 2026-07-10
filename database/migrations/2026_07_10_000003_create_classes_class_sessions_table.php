<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.class_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('class_schedule_id')
                ->constrained('classes.class_schedules')
                ->cascadeOnDelete();

            $table->date('date');

            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity');

            $table->foreignId('coach_id')
                ->constrained('classes.coaches')
                ->cascadeOnDelete();

            $table->string('status')->default('scheduled'); // scheduled | cancelled
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->unique(['class_schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.class_sessions');
    }
};
