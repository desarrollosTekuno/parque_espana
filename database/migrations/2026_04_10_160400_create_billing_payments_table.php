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
        Schema::create('billing.payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_account_id')
                ->constrained('memberships.accounts');
            $table->foreignId('club_id')
                ->constrained('clubs.clubs');
            $table->foreignId('payment_method_id')
                ->constrained('billing.payment_methods');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('reference')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('check_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users');
            $table->enum('status', ['registered', 'cancelled'])->default('registered');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing.payments');
    }
};
