<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('billing.credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_account_id')->constrained('memberships.accounts')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();

            $table->unique('membership_account_id');
        });

        Schema::connection('pgsql')->create('billing.credit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_account_id')->constrained('memberships.accounts')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);              // positivo = abono, negativo = uso
            $table->string('concept', 50);                 // annual_discount | overpayment | applied_to_charge
            $table->foreignId('charge_id')->nullable()->constrained('billing.charges')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('billing.payments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('billing.credit_movements');
        Schema::connection('pgsql')->dropIfExists('billing.credit_balances');
    }
};
