<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar índice actual
        DB::statement("
            DROP INDEX IF EXISTS members.members_locker_assignments_member_club_year_unique
        ");

        // Crear nuevo índice SOLO para clubes distintos a 2
        DB::statement("
            CREATE UNIQUE INDEX members_locker_assignments_member_club_year_unique
            ON members.locker_assignments (member_id, club_id, year)
            WHERE deleted_at IS NULL AND club_id != 2
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS members.members_locker_assignments_member_club_year_unique
        ");

        DB::statement("
            CREATE UNIQUE INDEX members_locker_assignments_member_club_year_unique
            ON members.locker_assignments (member_id, club_id, year)
            WHERE deleted_at IS NULL
        ");
    }
};
