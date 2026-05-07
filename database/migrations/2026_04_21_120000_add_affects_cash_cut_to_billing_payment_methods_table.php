<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing.payment_methods', function (Blueprint $table) {
            $table->boolean('affects_cash_cut')->default(true)->after('requires_check_number');
        });

        DB::table('billing.payment_methods')
            ->where('code', 'SERVICES')
            ->update(['affects_cash_cut' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing.payment_methods', function (Blueprint $table) {
            $table->dropColumn('affects_cash_cut');
        });
    }
};
