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
        Schema::create('memberships.account_groups', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->foreignId('account_group_id')
                ->nullable()
                ->after('id')
                ->constrained('memberships.account_groups');
        });

        $timestamp = now();
        $accounts = DB::table('memberships.accounts')
            ->select('id')
            ->orderBy('id')
            ->get();

        foreach ($accounts as $account) {
            $groupId = DB::table('memberships.account_groups')->insertGetId([
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('memberships.accounts')
                ->where('id', $account->id)
                ->update([
                    'account_group_id' => $groupId,
                    'updated_at' => $timestamp,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_group_id');
        });

        Schema::dropIfExists('memberships.account_groups');
    }
};
