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
        Schema::table('devices.commands', function (Blueprint $table) {
            $table->dropForeign('devices_commands_account_member_id_foreign');
        });

        Schema::table('devices.commands', function (Blueprint $table) {
            $table->unsignedBigInteger('account_member_id')->nullable()->change();
        });

        Schema::table('devices.commands', function (Blueprint $table) {
            $table->foreign('account_member_id')
                ->references('id')
                ->on('memberships.account_members')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices.commands', function (Blueprint $table) {
            $table->dropForeign('devices_commands_account_member_id_foreign');
        });

        Schema::table('devices.commands', function (Blueprint $table) {
            $table->unsignedBigInteger('account_member_id')->nullable(false)->change();
        });

        Schema::table('devices.commands', function (Blueprint $table) {
            $table->foreign('account_member_id')
                ->references('id')
                ->on('memberships.account_members')
                ->constrained();
        });
    }
};
