<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            // Los conceptos legados (import histórico) no se pueden borrar
            // de verdad: aunque hoy no tengan cargos, billing.charges.concept_id
            // sigue apuntando a ellos por code, y un delete físico rompería
            // esa referencia (o el unique de 'code' si algún día se vuelven
            // a necesitar). Soft delete: "eliminar" en el catálogo solo los
            // oculta, sin perder el historial.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
