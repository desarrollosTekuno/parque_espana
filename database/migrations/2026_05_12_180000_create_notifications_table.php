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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->nullable();
            $table->string('title', 150);
            $table->text('body');
            $table->unsignedTinyInteger('type')->default(0); // 0 manual, 1 automatic
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
