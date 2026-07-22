<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Speeds up whereHas('primaryHolder') and primary holder lookups
        Schema::table('memberships.account_members', function (Blueprint $table) {
            $table->index(['membership_account_id', 'is_primary_holder'], 'account_members_account_primary_idx');
        });

        // Speeds up the main listing filter: club_id + status + is_primary
        Schema::table('memberships.memberships', function (Blueprint $table) {
            $table->index(['club_id', 'status', 'is_primary'], 'memberships_club_status_primary_idx');
        });
    }

    public function down(): void
    {
        /* Schema::table('memberships.account_members', function (Blueprint $table) {
            $table->dropIndex('account_members_account_primary_idx');
        });

        Schema::table('memberships.memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_club_status_primary_idx');
        }); */
    }
};
