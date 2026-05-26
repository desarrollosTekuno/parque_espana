<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships.pending_age_transitions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_id')
                ->constrained('memberships.memberships')
                ->cascadeOnDelete();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            $table->foreignId('membership_account_id')
                ->constrained('memberships.accounts')
                ->cascadeOnDelete();

            $table->foreignId('target_membership_type_id')
                ->constrained('memberships.types');

            $table->string('transition_type'); // family_to_solidaria | solidaria_to_individual

            $table->decimal('monthly_fee', 10, 2);
            $table->boolean('has_multiple_clubs')->default(false);

            $table->string('status')->default('pending'); // pending | promoted | dismissed

            $table->timestamp('identified_at');
            $table->timestamp('promoted_at')->nullable();
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dismissal_reason')->nullable();

            $table->timestamps();

            $table->unique(['membership_account_id', 'member_id', 'transition_type', 'status'], 'unique_pending_transition');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships.pending_age_transitions');
    }
};
