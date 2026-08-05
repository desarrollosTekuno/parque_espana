<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Agregar access_code en account_members (por club, no por persona)
        Schema::table('memberships.account_members', function (Blueprint $table) {
            $table->string('access_code')->nullable()->after('is_primary_holder');
        });

        // Eliminar access_code de members (era incorrecto ponerlo ahí)
        Schema::table('members.members', function (Blueprint $table) {
            $table->dropUnique(['access_code']);
            $table->dropColumn('access_code');
        });
    }

    public function down(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            $table->string('access_code')->nullable()->unique()->after('email');
        });

        Schema::table('memberships.account_members', function (Blueprint $table) {
            $table->dropColumn('access_code');
        });
    }
};
