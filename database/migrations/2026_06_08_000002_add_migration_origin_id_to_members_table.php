<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            // Identificador de origen usado durante la migración de datos.
            // Permite reconstruir el contexto al correr importadores de forma individual.
            $table->string('migration_origin_id', 50)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            $table->dropColumn('migration_origin_id');
        });
    }
};
