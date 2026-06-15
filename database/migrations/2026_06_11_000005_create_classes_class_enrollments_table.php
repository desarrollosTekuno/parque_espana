<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.class_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('class_schedule_id')
                ->constrained('classes.class_schedules')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('member_id');
            $table->foreign('member_id')
                ->references('id')
                ->on('members.members')
                ->cascadeOnDelete();

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.class_enrollments');
    }
};
