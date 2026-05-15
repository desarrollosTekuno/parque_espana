<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mueve la lógica de edad al catálogo de tipos de documento y agrega tamaño máximo.
 *
 * 1. Elimina min_age / max_age del pivot type_required_documents (si existen).
 * 2. Agrega min_age, max_age y max_file_size_kb a catalogs.document_types.
 *
 * Semántica:
 *   - min_age / max_age : rango de edad del integrante para el que aplica este doc.
 *                         NULL = sin restricción en ese extremo.
 *   - max_file_size_kb  : tamaño máximo del archivo en KB.
 *                         NULL = usar el default del sistema (2 048 KB = 2 MB).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Quitar columnas del pivot (las pusimos ahí en la migración anterior)
        if (Schema::hasColumn('memberships.type_required_documents', 'min_age')) {
            Schema::table('memberships.type_required_documents', function (Blueprint $table) {
                $table->dropColumn(['min_age', 'max_age']);
            });
        }

        // 2. Agregar al catálogo de tipos de documento
        Schema::table('catalogs.document_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_age')->nullable()->after('allowed_extensions')
                ->comment('Edad mínima del integrante para solicitar este documento (null = sin límite)');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age')
                ->comment('Edad máxima del integrante para solicitar este documento (null = sin límite)');
            $table->unsignedSmallInteger('max_file_size_kb')->nullable()->after('max_age')
                ->comment('Tamaño máximo del archivo en KB (null = default 5 120 KB / 5 MB)');
        });
    }

    public function down(): void
    {
        Schema::table('catalogs.document_types', function (Blueprint $table) {
            $table->dropColumn(['min_age', 'max_age', 'max_file_size_kb']);
        });

        Schema::table('memberships.type_required_documents', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_age')->nullable()->after('number_files');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');
        });
    }
};
