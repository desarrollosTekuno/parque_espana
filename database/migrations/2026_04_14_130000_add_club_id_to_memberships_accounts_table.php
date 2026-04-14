<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->foreignId('club_id')
                ->nullable()
                ->after('account_group_id')
                ->constrained('clubs.clubs');
        });

        $accountClubMap = DB::table('memberships.memberships')
            ->select('membership_account_id', 'club_id', 'is_primary', 'status', 'id')
            ->orderByDesc('is_primary')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get()
            ->groupBy('membership_account_id')
            ->map(fn ($memberships) => $memberships->first()?->club_id)
            ->filter();

        foreach ($accountClubMap as $accountId => $clubId) {
            DB::table('memberships.accounts')
                ->where('id', $accountId)
                ->update([
                    'club_id' => $clubId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
        });
    }
};
