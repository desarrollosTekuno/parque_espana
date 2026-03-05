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
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // icon and background image
            $table->string('icon')->nullable();
            $table->string('background_image')->nullable();
            $table->string('description')->nullable();
            $table->string('reservation_type');
            $table->integer('capacity')->nullable();
            $table->integer('slot_duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('club_id')->constrained('clubs');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
