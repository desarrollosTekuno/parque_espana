<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('classes.class_enrollments', function (Blueprint $table) {
            $table->date('reservation_date')->after('member_id');

            $table->unsignedBigInteger('reserved_by_member_id')->after('reservation_date');
            $table->foreign('reserved_by_member_id')
                ->references('id')
                ->on('members.members')
                ->restrictOnDelete();

            $table->dropSoftDeletes();
        });

        DB::statement('
            CREATE UNIQUE INDEX class_enrollments_active_unique
            ON classes.class_enrollments (class_schedule_id, member_id, reservation_date)
            WHERE cancelled_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS classes.class_enrollments_active_unique');

        Schema::table('classes.class_enrollments', function (Blueprint $table) {
            $table->softDeletes();
            $table->dropForeign(['reserved_by_member_id']);
            $table->dropColumn(['reservation_date', 'reserved_by_member_id']);
        });
    }
};
