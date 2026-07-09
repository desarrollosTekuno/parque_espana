<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('classes.class_enrollments', function (Blueprint $table) {
            $table->date('reservation_date')->nullable()->after('member_id');

            $table->unsignedBigInteger('reserved_by_member_id')->nullable()->after('reservation_date');
            $table->foreign('reserved_by_member_id')
                ->references('id')
                ->on('members.members')
                ->nullOnDelete();

            $table->index(['class_schedule_id', 'reservation_date'], 'class_enrollments_schedule_date_idx');
            $table->index(['member_id', 'reservation_date'], 'class_enrollments_member_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('classes.class_enrollments', function (Blueprint $table) {
            $table->dropIndex('class_enrollments_schedule_date_idx');
            $table->dropIndex('class_enrollments_member_date_idx');
            $table->dropForeign(['reserved_by_member_id']);
            $table->dropColumn(['reservation_date', 'reserved_by_member_id']);
        });
    }
};
