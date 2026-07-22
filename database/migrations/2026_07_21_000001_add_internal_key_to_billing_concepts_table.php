<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            // Clave del catálogo contable/legado del club. Ej: código del
            // sistema anterior. No se usa para lógica del sistema (para eso
            // está "code"), solo como referencia visible para el admin.
            $table->string('internal_key', 50)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            $table->dropColumn('internal_key');
        });
    }
};
