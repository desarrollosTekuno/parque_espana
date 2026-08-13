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
        Schema::create('devices.commands', function (Blueprint $table) {
            $table->id();
            $table->string('action', 30);

            $table->json('data');

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'error',
            ])->default('pending');

            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->foreignId('member_id')
                ->constrained('members.members');

            $table->foreignId('device_id')
                ->constrained('devices.devices');

            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices.commands');
    }
};
