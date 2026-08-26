<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            // Si el importe de este concepto se puede escribir a mano en
            // "Agregar concepto de cobro" (Collections/Index.vue) o si debe
            // quedar fijo al monto configurado (default_amount / override por
            // parque, ver ChargeConcept::resolveAmountForClub) para evitar
            // que cobranza capture importes equivocados. true por default
            // para no cambiar el comportamiento actual de los conceptos ya
            // existentes.
            $table->boolean('allows_manual_amount')->default(true)->after('default_amount');
        });
    }

    public function down(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            $table->dropColumn('allows_manual_amount');
        });
    }
};
