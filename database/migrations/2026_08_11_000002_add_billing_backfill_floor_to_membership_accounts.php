<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            // Cuando se condona el adeudo pendiente al dar de baja una cuenta
            // (AccountCancellationController::waivePendingCharges), este es el
            // "piso" a partir del cual ensureMonthlyChargesUpToToday puede volver
            // a rellenar mensualidad faltante. Sin esto, al reactivar, el backfill
            // rellenaba mensualidad de meses previos a la baja que ya se habían
            // condonado (o que nunca se habían facturado), como si el adeudo nunca
            // se hubiera perdonado.
            $table->date('billing_backfill_floor')->nullable()->after('cancellation_type');
        });
    }

    public function down(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->dropColumn('billing_backfill_floor');
        });
    }
};
