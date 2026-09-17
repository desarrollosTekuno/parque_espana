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
        Schema::create('devices.scheduled_daily_passes', function (Blueprint $table) {
            $table->id();
            $table->date('visit_date');
            $table->string('notify_email', 255);
            $table->enum('status', [ 'pending', 'processed', 'failed' ])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedBigInteger('charge_id');
            $table->foreignId('club_id')->constrained('clubs.clubs');
            $table->foreignId('account_member_id')->constrained('memberships.account_members');

            $table->timestamps();

            $table->index(['visit_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices.scheduled_daily_passes');
    }
};
