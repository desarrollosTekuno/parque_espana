<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            // Si el concepto se puede pagar desde la app móvil. Se enforce en
            // ChargePaymentController — un cargo de un concepto en false no se
            // puede liquidar desde el endpoint de pago móvil.
            $table->boolean('is_mobile_payable')->default(true)->after('allows_partial_payments');

            // Si el concepto reparte su monto total 50/50 entre ambos parques
            // para un socio interclub. Por ahora es solo informativo (no
            // dispara ninguna división automática de cargos) — no confundir
            // con memberships.memberships.billing_split_mode, que es un
            // mecanismo distinto a nivel membresía, no a nivel concepto.
            $table->boolean('splits_between_parks')->default(false)->after('is_mobile_payable');
        });
    }

    public function down(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            $table->dropColumn(['is_mobile_payable', 'splits_between_parks']);
        });
    }
};
