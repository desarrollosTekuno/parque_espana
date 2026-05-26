<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('members.locker_assignment_histories', function (Blueprint $table) {

            $table->id();
            $table->foreignId('locker_assignment_id')
                ->constrained('members.locker_assignments')
                ->cascadeOnDelete();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            $table->foreignId('old_locker_id')
                ->nullable()
                ->constrained('members.lockers')
                ->cascadeOnDelete();

            $table->foreignId('new_locker_id')
                ->constrained('members.lockers')
                ->cascadeOnDelete();

            $table->timestamp('changed_at');

            $table->foreignId('changed_by')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.locker_assignment_histories');
    }
};
