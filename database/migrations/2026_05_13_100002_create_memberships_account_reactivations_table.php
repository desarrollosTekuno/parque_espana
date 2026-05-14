<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships.account_reactivations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_account_id')
                ->constrained('memberships.accounts')
                ->cascadeOnDelete();

            $table->foreignId('reactivated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reactivated_at');
            $table->boolean('requires_documentation')->default(false);
            $table->boolean('enrollment_fee_applied')->default(false);
            $table->decimal('enrollment_fee_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships.account_reactivations');
    }
};
