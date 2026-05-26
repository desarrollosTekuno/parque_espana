<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing.spei_orders', function (Blueprint $table) {
            $table->id();

            // Cuenta y club al que pertenecen los cargos
            $table->unsignedBigInteger('membership_account_id');
            $table->unsignedBigInteger('club_id');

            // Método de pago local (billing.payment_methods) — se guarda aquí
            // para poder usarlo al registrar el pago cuando llegue el webhook
            $table->unsignedBigInteger('payment_method_id');

            // Referencia en Conekta
            $table->string('conekta_order_id')->unique();
            $table->string('conekta_charge_id')->nullable();

            // Datos del CLABE que se muestran al socio
            $table->string('clabe', 18);
            $table->string('bank')->nullable();

            // Monto total de la orden
            $table->decimal('amount', 12, 2);

            // Cuándo vence el CLABE
            $table->timestamp('expires_at');

            // Estado de la orden SPEI
            $table->enum('status', ['pending', 'paid', 'expired'])->default('pending');

            // Cargos que cubre esta orden [{charge_id, amount}]
            $table->json('applications');

            // Se llena cuando el webhook confirma el pago
            $table->unsignedBigInteger('payment_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('membership_account_id')
                  ->references('id')->on('memberships.accounts');

            $table->foreign('club_id')
                  ->references('id')->on('clubs.clubs');

            $table->foreign('payment_method_id')
                  ->references('id')->on('billing.payment_methods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing.spei_orders');
    }
};
