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
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('cascade');

            $table->unsignedBigInteger('notification_recipient_id')->nullable();
            $table->foreign('notification_recipient_id')->references('id')->on('notification_recipients')->nullOnDelete();

            $table->string('channel', 20);
            $table->string('destination', 255)->nullable();
            $table->string('provider', 100)->nullable();
            $table->string('status', 20);
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};
