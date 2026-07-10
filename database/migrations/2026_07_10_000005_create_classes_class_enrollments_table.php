<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.class_enrollments', function (Blueprint $table) {
            $table->id();

            $table->timestamp('attended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('class_session_id')
                ->constrained('classes.class_sessions')
                ->cascadeOnDelete();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            $table->foreignId('enrolled_by_member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.class_enrollments');
    }
};
