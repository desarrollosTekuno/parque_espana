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
        Schema::create('memberships.memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_account_id')
                ->constrained('memberships.accounts');

            $table->foreignId('club_id')
                ->constrained('clubs.clubs');

            $table->foreignId('membership_type_id')
                ->constrained('memberships.types');

            $table->foreignId('origin_membership_type_id')
                ->nullable()
                ->constrained('memberships.types');

            $table->boolean('is_primary')->default(true);

            $table->decimal('monthly_fee', 12, 2);

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->enum('status', [
                'pending',
                'active',
                'suspended',
                'cancelled'
            ])->default('pending');

            $table->timestamps();

            $table->unique(['membership_account_id', 'club_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.memberships');
    }
};
