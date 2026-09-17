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
        Schema::create('devices.scheduled_daily_pass_visitors', function (Blueprint $table) {
            $table->id();

            $table->string('first_name', 150);
            $table->string('last_name', 150);
            $table->string('phone', 30)->nullable();

            $table->foreignId('scheduled_day_pass_id')->constrained('devices.scheduled_day_passes')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices.scheduled_daily_pass_visitors');
    }
};
