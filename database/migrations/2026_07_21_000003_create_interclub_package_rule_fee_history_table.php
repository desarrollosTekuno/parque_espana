<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ver 2026_07_21_000002_create_pricing_rule_fee_history_table — misma idea,
 * para memberships.interclub_package_rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships.interclub_package_rule_fee_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interclub_package_rule_id')
                ->constrained('memberships.interclub_package_rules')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('monthly_fee', 12, 2);
            $table->decimal('inscription_fee', 12, 2)->nullable();
            $table->timestamps();

            // Nombre corto explícito: el autogenerado por Laravel excede el
            // límite de 63 caracteres de Postgres y se trunca al mismo
            // prefijo que el constraint de la llave foránea, chocando con él.
            $table->unique(['interclub_package_rule_id', 'year'], 'interclub_pkg_rule_fee_history_rule_year_unique');
        });

        $now = now();
        $year = $now->year;

        $rows = DB::table('memberships.interclub_package_rules')->get(['id', 'monthly_fee', 'inscription_fee']);

        foreach ($rows as $row) {
            DB::table('memberships.interclub_package_rule_fee_history')->insert([
                'interclub_package_rule_id' => $row->id,
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
        Schema::dropIfExists('memberships.interclub_package_rule_fee_history');
    }
};
