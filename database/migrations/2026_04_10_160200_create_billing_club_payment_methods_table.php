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
        Schema::create('billing.club_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')
                ->constrained('clubs.clubs');
            $table->foreignId('payment_method_id')
                ->constrained('billing.payment_methods');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['club_id', 'payment_method_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing.club_payment_methods');
    }
};
