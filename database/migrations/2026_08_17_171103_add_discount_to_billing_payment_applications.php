<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.payment_applications', function (Blueprint $table) {
            // Cuánto de applied_amount + discount que cubre este renglón es
            // en realidad un descuento (no dinero real) — p. ej. el mes
            // libre o la parte proporcional de un pago de anualidad (ver
            // AnnualPaymentService/CollectionController::storePayment). Se
            // guarda por renglón (no en el pago completo) por la misma
            // razón que subtotal/iva: un mismo cargo puede cubrirse con más
            // de una forma de pago, y el ticket necesita saber exactamente
            // cuánto descuento le tocó a cada cargo.
            $table->decimal('discount', 12, 2)->nullable()->after('iva');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payment_applications', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
