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
        Schema::create('memberships.membership_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_id')
                ->constrained('memberships.memberships');

            $table->foreignId('old_membership_type_id')
                ->nullable()
                ->constrained('memberships.types')
                ->nullOnDelete();

            $table->foreignId('new_membership_type_id')
                ->constrained('memberships.types');

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users');

            $table->date('effective_date');
            $table->string('reason')->nullable();

            $table->decimal('previous_monthly_fee', 12, 2)->nullable();
            $table->decimal('new_monthly_fee', 12, 2)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.membership_history');
    }
};
