<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS members.members_locker_assignments_member_club_year_unique
        ");

        DB::statement("
            CREATE UNIQUE INDEX members_locker_assignments_locker_club_year_unique
            ON members.locker_assignments (locker_id, club_id, year)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS members.members_locker_assignments_locker_club_year_unique
        ");

        DB::statement("
            CREATE UNIQUE INDEX members_locker_assignments_member_club_year_unique
            ON members.locker_assignments (member_id, club_id, year)
            WHERE deleted_at IS NULL
        ");
    }
};