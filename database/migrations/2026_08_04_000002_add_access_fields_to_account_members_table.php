<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('memberships.account_members', function (Blueprint $table) {
            $table->string('access_code')->nullable()->after('is_primary_holder');
            $table->timestampTz('access_valid_until')->nullable()->after('access_code');
            $table->string('access_status')->default('active')->after('access_valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('memberships.account_members', function (Blueprint $table) {
            $table->dropColumn(['access_code', 'access_valid_until', 'access_status']);
        });
    }
};
