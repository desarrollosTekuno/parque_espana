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
        Schema::create('members.payment_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            // ID de la fuente de pago en Conekta (src_xxx)
            $table->string('conekta_payment_source_id')->unique();

            // Datos de la tarjeta (solo los últimos 4 dígitos, nunca datos sensibles)
            $table->string('card_type')->nullable();        // visa, mastercard, etc.
            $table->string('card_last4')->nullable();       // 4242
            $table->string('card_brand')->nullable();       // VISA, MASTERCARD
            $table->string('card_exp_month')->nullable();   // 12
            $table->string('card_exp_year')->nullable();    // 2027
            $table->string('cardholder_name')->nullable();  // Juan García

            // Si es la fuente de pago predeterminada del miembro
            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members.payment_sources');
    }
};
