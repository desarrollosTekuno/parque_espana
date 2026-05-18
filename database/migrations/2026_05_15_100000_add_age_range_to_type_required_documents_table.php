<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega min_age y max_age al pivot memberships.type_required_documents.
 *
 * Permite configurar documentos que solo aplican a ciertos rangos de edad.
 * Ejemplo: INE (min_age = 18), acta de nacimiento (max_age = 17).
 *
 * NULL = sin restricción de edad en ese extremo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema::connection('memberships')->table('type_required_documents', function (Blueprint $table) {
        //     $table->unsignedTinyInteger('min_age')->nullable()->after('number_files')
        //         ->comment('Edad mínima del integrante para requerir este documento (null = sin límite)');
        //     $table->unsignedTinyInteger('max_age')->nullable()->after('min_age')
        //         ->comment('Edad máxima del integrante para requerir este documento (null = sin límite)');
        // });
        Schema::table('memberships.type_required_documents', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_age')->nullable()->after('number_files')
                ->comment('Edad mínima del integrante para requerir este documento (null = sin límite)');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age')
                ->comment('Edad máxima del integrante para requerir este documento (null = sin límite)');
        });
    }

    public function down(): void
    {
        // Schema::connection('memberships')->table('type_required_documents', function (Blueprint $table) {
        //     $table->dropColumn(['min_age', 'max_age']);
        // });
        Schema::table('memberships.type_required_documents', function (Blueprint $table) {
            $table->dropColumn(['min_age', 'max_age']);
        });
    }
};
