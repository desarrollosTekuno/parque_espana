<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reemplaza el unique constraint (name, guard_name) por (name, guard_name, context_id)
     * para permitir el mismo nombre de rol en distintos contextos de club.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_unique');
            $table->unique(['name', 'guard_name', 'context_id'], 'roles_name_guard_name_context_unique');
        });
    }

    public function down(): void
    {
        // Antes de restaurar el constraint original (name, guard_name),
        // eliminamos duplicados dejando solo el registro más reciente por combinación.
        DB::statement("
            DELETE FROM roles
            WHERE id NOT IN (
                SELECT MAX(id)
                FROM roles
                GROUP BY name, guard_name
            )
        ");

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_context_unique');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }
};
