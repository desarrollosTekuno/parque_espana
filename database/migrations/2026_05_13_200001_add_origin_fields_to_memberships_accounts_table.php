<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('origin_account_id')->nullable()->after('account_group_id');
            $table->string('separation_reason')->nullable()->after('origin_account_id');

            $table->foreign('origin_account_id')
                ->references('id')
                ->on('memberships.accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->dropForeign(['origin_account_id']);
            $table->dropColumn(['origin_account_id', 'separation_reason']);
        });
    }
};
