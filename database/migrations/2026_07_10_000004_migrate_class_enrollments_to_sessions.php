<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // No hay reservaciones reales todavia en produccion, solo datos de prueba locales.
        DB::table('classes.class_enrollments')->delete();

        DB::statement('DROP INDEX IF EXISTS classes.class_enrollments_active_unique');

        Schema::table('classes.class_enrollments', function (Blueprint $table) {
            $table->dropForeign(['class_schedule_id']);
            $table->dropColumn(['class_schedule_id', 'reservation_date']);

            $table->foreignId('class_session_id')
                ->after('id')
                ->constrained('classes.class_sessions')
                ->cascadeOnDelete();
        });

        DB::statement('
            CREATE UNIQUE INDEX class_enrollments_session_member_unique
            ON classes.class_enrollments (class_session_id, member_id)
            WHERE cancelled_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS classes.class_enrollments_session_member_unique');

        DB::table('classes.class_enrollments')->delete();

        Schema::table('classes.class_enrollments', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
            $table->dropColumn('class_session_id');

            $table->unsignedBigInteger('class_schedule_id')->after('id');
            $table->foreign('class_schedule_id')
                ->references('id')
                ->on('classes.class_schedules')
                ->cascadeOnDelete();

            $table->date('reservation_date')->after('member_id');
        });
    }
};
