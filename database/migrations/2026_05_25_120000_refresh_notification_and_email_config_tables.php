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
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('notification_attachments');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_status_catalogs');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('email_configs');

        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('code', 20)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_status_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('code', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('email_configs', function (Blueprint $table) {
            $table->id();
            $table->string('profile_name', 120);
            $table->string('template_name', 50)->default('email_template');
            $table->string('host', 150);
            $table->unsignedSmallInteger('port');
            $table->string('username', 150);
            $table->text('password');
            $table->string('encryption', 10)->nullable();
            $table->string('from_address', 150);
            $table->string('from_name', 150);
            $table->boolean('is_active')->default(true);

            $table->index('is_active');

            $table->unsignedBigInteger('entity_id');
            $table->foreign('entity_id')->references('id')->on('clubs.clubs')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->nullable();
            $table->string('title', 150);
            $table->text('body');
            $table->char('scope', 1)->default('I');
            $table->unsignedTinyInteger('type')->default(0);
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->date('sent_date')->nullable();
            $table->time('sent_time')->nullable();

            $table->unsignedBigInteger('channel_id');
            $table->foreign('channel_id')->references('id')->on('notification_channels');

            $table->unsignedBigInteger('status_id');
            $table->foreign('status_id')->references('id')->on('notification_status_catalogs');

            $table->unsignedBigInteger('club_id')->nullable();
            $table->foreign('club_id')->references('id')->on('clubs.clubs')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();

            $table->string('destination', 255)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('error_message')->nullable();
            $table->tinyInteger('attempts')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->unsignedBigInteger('notification_id');
            $table->foreign('notification_id')->references('id')->on('notifications')->cascadeOnDelete();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_attachments', function (Blueprint $table) {
            $table->id();

            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->unsignedBigInteger('notification_id');
            $table->foreign('notification_id')->references('id')->on('notifications')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

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
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('notification_attachments');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('email_configs');
        Schema::dropIfExists('notification_status_catalogs');
        Schema::dropIfExists('notification_channels');
    }
};
