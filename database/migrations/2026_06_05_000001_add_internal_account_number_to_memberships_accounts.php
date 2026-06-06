<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->string('internal_account_number', 100)
                ->nullable()
                ->unique()
                ->after('membership_number');
        });
    }

    public function down(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->dropColumn('internal_account_number');
        });
    }
};
