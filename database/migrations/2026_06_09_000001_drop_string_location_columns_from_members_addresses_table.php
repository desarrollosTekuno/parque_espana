<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members.addresses', function (Blueprint $table) {
            $table->dropColumn(['city', 'state', 'country']);
        });
    }

    public function down(): void
    {
        Schema::table('members.addresses', function (Blueprint $table) {
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable()->default('México');
        });
    }
};
