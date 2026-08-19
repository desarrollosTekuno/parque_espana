<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El patrón de descuento por pago de anualidad (enero = mes completo,
     * febrero = medio mes, etc.) no cambia de un año a otro — tenerlo
     * repetido por año solo obligaba a volver a capturarlo cada año sin
     * ninguna razón real. Se vuelve una sola configuración vigente para
     * cualquier año (ver AnnualDiscountRule::findApplicable).
     */
    public function up(): void
    {
        Schema::connection('pgsql')->table('billing.annual_discount_rules', function (Blueprint $table) {
            $table->dropUnique(['year', 'pay_by_month']);
        });

        // Si por algún motivo hay más de una fila para el mismo
        // pay_by_month (capturadas en años distintos), se conserva la más
        // reciente y se descartan las demás antes de poder aplicar la
        // restricción única sobre pay_by_month solo.
        $duplicateIds = DB::table('billing.annual_discount_rules')
            ->select('pay_by_month', DB::raw('array_agg(id ORDER BY id DESC) as ids'))
            ->groupBy('pay_by_month')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicateIds as $row) {
            $ids = is_string($row->ids) ? json_decode(str_replace(['{', '}'], ['[', ']'], $row->ids)) : $row->ids;
            $idsToDelete = array_slice($ids, 1);
            if (!empty($idsToDelete)) {
                DB::table('billing.annual_discount_rules')->whereIn('id', $idsToDelete)->delete();
            }
        }

        Schema::connection('pgsql')->table('billing.annual_discount_rules', function (Blueprint $table) {
            $table->dropColumn('year');
            $table->unique('pay_by_month');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('billing.annual_discount_rules', function (Blueprint $table) {
            $table->dropUnique(['pay_by_month']);
            $table->unsignedSmallInteger('year')->default(now()->year)->after('id');
        });

        Schema::connection('pgsql')->table('billing.annual_discount_rules', function (Blueprint $table) {
            $table->unique(['year', 'pay_by_month']);
        });
    }
};
