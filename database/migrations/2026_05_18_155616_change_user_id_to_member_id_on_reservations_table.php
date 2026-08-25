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
         Schema::table('reservations.reservations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->renameColumn('user_id', 'member_id');
            $table->foreign('member_id')->references('id')->on('members.members');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations.reservations', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->renameColumn('member_id', 'user_id');
            // $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
