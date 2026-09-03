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
        Schema::create('devices.guest_users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 50);
            $table->unsignedInteger('active_cards_count')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('device_id')->constrained('devices.devices');
            $table->timestamps();

            $table->unique(['device_id', 'employee_id']);
            $table->index(['device_id', 'status', 'active_cards_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices.guest_users');
    }
};
