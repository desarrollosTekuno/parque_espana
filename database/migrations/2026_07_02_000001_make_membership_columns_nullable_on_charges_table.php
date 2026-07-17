<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE billing.charges ALTER COLUMN membership_account_id DROP NOT NULL');
        DB::statement('ALTER TABLE billing.charges ALTER COLUMN membership_id DROP NOT NULL');
        DB::statement('ALTER TABLE billing.charges ALTER COLUMN member_id DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // DB::statement('ALTER TABLE billing.charges ALTER COLUMN membership_account_id SET NOT NULL');
        // DB::statement('ALTER TABLE billing.charges ALTER COLUMN membership_id SET NOT NULL');
        // DB::statement('ALTER TABLE billing.charges ALTER COLUMN member_id SET NOT NULL');
    }
};
