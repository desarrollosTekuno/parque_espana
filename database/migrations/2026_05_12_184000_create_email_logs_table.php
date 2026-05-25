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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();

            $table->string('to_email', 150);
            $table->string('subject', 255);
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->index('entity_id');
            $table->index('status');
            $table->index('sent_at');

            $table->unsignedBigInteger('entity_id');
            $table->foreign('entity_id')
                ->references('id')
                ->on('clubs.clubs')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('email_config_id')->nullable();
            $table->foreign('email_config_id')
                ->references('id')
                ->on('email_configs')
                ->nullOnDelete();

            $table->unsignedBigInteger('notification_id')->nullable();
            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
