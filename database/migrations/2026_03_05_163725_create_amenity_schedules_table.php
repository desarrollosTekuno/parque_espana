<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('amenity_schedules', function (Blueprint $table) {
            $table->id();
            $table->integer('day_of_week');
            $table->time('open_time');
            $table->time('close_time');
            $table->foreignId('amenity_id')->constrained('amenities');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_schedules');
    }
};
