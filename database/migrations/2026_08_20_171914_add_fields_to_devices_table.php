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
        Schema::table('devices.devices', function (Blueprint $table) {
            $table->string('user', 50)->nullable()->after('ip');
            $table->string('password', 50)->nullable()->after('user');
            $table->string('port', 10)->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices.devices', function (Blueprint $table) {
            $table->dropColumn(['user', 'password', 'port']);
        });
    }
};
