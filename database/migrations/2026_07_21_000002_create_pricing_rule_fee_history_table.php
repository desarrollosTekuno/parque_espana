<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cuotas por año para reglas de precio. Antes monthly_fee e
 * inscription_fee vivían fijos en memberships.pricing_rules, así que un
 * ajuste anual significaba editar cada regla a mano y no quedaba registro
 * de qué se cobró en años anteriores (necesario para la migración de datos).
 * Esta tabla es la nueva fuente de verdad; la migración siguiente respalda
 * los montos actuales como el año en curso, y otra después elimina las
 * columnas fijas de pricing_rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships.pricing_rule_fee_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_rule_id')
                ->constrained('memberships.pricing_rules')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('monthly_fee', 12, 2);
            $table->decimal('inscription_fee', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['pricing_rule_id', 'year']);
        });

        $now = now();
        $year = $now->year;

        $rows = DB::table('memberships.pricing_rules')->get(['id', 'monthly_fee', 'inscription_fee']);

        foreach ($rows as $row) {
            DB::table('memberships.pricing_rule_fee_history')->insert([
                'pricing_rule_id' => $row->id,
                'year' => $year,
                'monthly_fee' => $row->monthly_fee,
                'inscription_fee' => $row->inscription_fee,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships.pricing_rule_fee_history');
    }
};
