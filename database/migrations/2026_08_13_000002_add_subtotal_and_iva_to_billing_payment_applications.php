<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.payment_applications', function (Blueprint $table) {
            // billing.payments.subtotal/iva es el total del PAGO completo,
            // que puede cubrir varios cargos con conceptos distintos (unos
            // con IVA, otros sin) — no alcanza para saber cuánto de ese
            // subtotal/IVA le corresponde a un cargo específico, sobre todo
            // cuando un mismo cargo se cubre con más de un pago (dividido en
            // varias formas de pago, ver PaymentRegistrationService::registerSplit).
            // Aquí se guarda el desglose por línea (por cargo cubierto), para
            // poder sumar exactamente lo que le tocó a un cargo en particular
            // — y mostrarlo así en el ticket (ver PaymentTicketService).
            $table->decimal('subtotal', 12, 2)->nullable()->after('applied_amount');
            $table->decimal('iva', 12, 2)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payment_applications', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'iva']);
        });
    }
};
