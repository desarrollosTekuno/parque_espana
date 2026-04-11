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
        Schema::create('billing.payment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')
                ->constrained('billing.payments');
            $table->foreignId('charge_id')
                ->constrained('billing.charges');
            $table->decimal('applied_amount', 12, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'charge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing.payment_applications');
    }
};
