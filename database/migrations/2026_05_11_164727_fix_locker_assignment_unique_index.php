<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar constraints viejos
        DB::statement("
            ALTER TABLE members.locker_assignments
            DROP CONSTRAINT IF EXISTS members_locker_assignments_locker_id_year_unique
        ");

        DB::statement("
            ALTER TABLE members.locker_assignments
            DROP CONSTRAINT IF EXISTS members_locker_assignments_member_id_year_unique
        ");

        // Crear índices únicos ignorando soft deleted
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS members_locker_assignments_locker_id_year_unique
            ON members.locker_assignments (locker_id, year)
            WHERE deleted_at IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS members_locker_assignments_member_id_year_unique
            ON members.locker_assignments (member_id, year)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS members.members_locker_assignments_locker_id_year_unique
        ");

        DB::statement("
            DROP INDEX IF EXISTS members.members_locker_assignments_member_id_year_unique
        ");
    }
};