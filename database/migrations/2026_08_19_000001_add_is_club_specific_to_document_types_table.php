<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Algunos tipos de documento (carta de recomendación, formato de solicitud)
 * no son el mismo archivo entre parques aunque el socio comparta cuenta en
 * varios: cada parque exige su propia versión. Esta bandera distingue esos
 * tipos de documento de los que sí son válidos para todos los parques del
 * socio (INE, comprobante de domicilio, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogs.document_types', function (Blueprint $table) {
            $table->boolean('is_club_specific')->default(false)->after('max_file_size_kb');
        });
    }

    public function down(): void
    {
        Schema::table('catalogs.document_types', function (Blueprint $table) {
            $table->dropColumn('is_club_specific');
        });
    }
};
