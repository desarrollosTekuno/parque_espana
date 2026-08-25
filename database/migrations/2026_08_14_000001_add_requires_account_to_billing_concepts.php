<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            // Si este concepto necesita estar ligado a una cuenta de socio
            // para poder cobrarse (la inmensa mayoría). false = se puede
            // vender "sin cuenta" (p. ej. un pase diario a un visitante sin
            // membresía) — ver PaymentRegistrationService::resolveCharges /
            // ensureChargesBelongToClub, que antes solo permitían esto para
            // el código CAFETERIA_PASS a modo de caso especial.
            $table->boolean('requires_account')->default(true)->after('is_active');
        });

        // CAFETERIA_PASS ya se vende sin cuenta desde antes de este cambio
        // (ver CafeteriaCheckoutService) — se formaliza aquí con el flag en
        // vez de quedar hardcodeado por código en el servicio de pagos.
        DB::table('billing.concepts')
            ->where('code', 'CAFETERIA_PASS')
            ->update(['requires_account' => false]);
    }

    public function down(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            $table->dropColumn('requires_account');
        });
    }
};
