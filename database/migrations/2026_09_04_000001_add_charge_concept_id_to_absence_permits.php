<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El permiso por ausencia ahora se registra eligiendo uno de dos conceptos
 * (CUOTA_PERMISO = 25%, CUOTA_75_PERMISO = 75%) en vez de capturar un
 * porcentaje libre — el cargo del mes durante la vigencia del permiso pasa a
 * cobrarse bajo ese concepto (en vez de quedarse como mensualidad normal con
 * el monto reducido), para que se distinga en Cobranza y en el ticket. Ver
 * MembershipChargeService::resolveMonthlyFeeConceptForPeriod.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships.absence_permits', function (Blueprint $table) {
            $table->foreignId('charge_concept_id')
                ->nullable()
                ->after('charge_percentage')
                ->constrained('billing.concepts')
                ->nullOnDelete();
        });

        // Backfill de permisos existentes: 25% -> CUOTA_PERMISO, 75% -> CUOTA_75_PERMISO.
        $conceptIds = DB::table('billing.concepts')
            ->whereIn('code', ['CUOTA_PERMISO', 'CUOTA_75_PERMISO'])
            ->pluck('id', 'code');

        if ($conceptIds->has('CUOTA_PERMISO')) {
            DB::table('memberships.absence_permits')
                ->where('charge_percentage', 25)
                ->update(['charge_concept_id' => $conceptIds['CUOTA_PERMISO']]);
        }

        if ($conceptIds->has('CUOTA_75_PERMISO')) {
            DB::table('memberships.absence_permits')
                ->where('charge_percentage', 75)
                ->update(['charge_concept_id' => $conceptIds['CUOTA_75_PERMISO']]);
        }
    }

    public function down(): void
    {
        Schema::table('memberships.absence_permits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charge_concept_id');
        });
    }
};
