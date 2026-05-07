<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('memberships.memberships', function (Blueprint $table) {
            $table->decimal('monthly_fee_total', 12, 2)->nullable()->after('monthly_fee');
            $table->decimal('monthly_fee_share', 12, 2)->nullable()->after('monthly_fee_total');
            $table->string('billing_split_mode', 30)->nullable()->after('monthly_fee_share');
        });

        DB::table('memberships.memberships')
            ->update([
                'monthly_fee_total' => DB::raw('monthly_fee'),
                'monthly_fee_share' => DB::raw('monthly_fee'),
                'billing_split_mode' => 'single',
            ]);
    }

    public function down(): void
    {
        Schema::table('memberships.memberships', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_fee_total',
                'monthly_fee_share',
                'billing_split_mode',
            ]);
        });
    }
};
