<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * monthly_fee/inscription_fee ya viven en las tablas de historial por año
 * (ver las dos migraciones anteriores, que respaldaron los valores actuales
 * como el año en curso antes de esto). Las reglas ahora son pura lógica de
 * emparejamiento; el monto siempre se resuelve vía
 * PricingRule::resolveMonthlyFee()/resolveInscriptionFee() (y sus
 * equivalentes en InterclubPackageRule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships.pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['monthly_fee', 'inscription_fee']);
        });

        Schema::table('memberships.interclub_package_rules', function (Blueprint $table) {
            $table->dropColumn(['monthly_fee', 'inscription_fee']);
        });
    }

    public function down(): void
    {
        Schema::table('memberships.pricing_rules', function (Blueprint $table) {
            $table->decimal('monthly_fee', 12, 2)->nullable()->after('requires_multiple_clubs');
            $table->decimal('inscription_fee', 12, 2)->nullable()->after('monthly_fee');
        });

        Schema::table('memberships.interclub_package_rules', function (Blueprint $table) {
            $table->decimal('inscription_fee', 12, 2)->nullable()->after('requires_active_source_membership');
            $table->decimal('monthly_fee', 12, 2)->nullable()->after('inscription_fee');
        });
    }
};
