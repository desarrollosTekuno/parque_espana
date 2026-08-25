<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            // Desglose informativo del monto ya cobrado (amount): se calcula
            // cargo por cargo según si el concepto de cada uno factura IVA en
            // el parque al que pertenece (ver
            // ChargeConcept::resolveAppliesIvaForClub) y se suma — un mismo
            // pago puede cubrir cargos de conceptos distintos, unos con IVA y
            // otros sin. Nullable porque los pagos ya registrados antes de
            // este campo no lo tienen calculado.
            $table->decimal('subtotal', 12, 2)->nullable()->after('amount');
            $table->decimal('iva', 12, 2)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'iva']);
        });
    }
};
