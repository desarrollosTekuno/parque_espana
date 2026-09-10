<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_app.variables', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->change();
        });

        $passwordVariable = DB::table('mobile_app.variables')
            ->where('name', 'default_user_password')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        if ($passwordVariable) {
            DB::table('mobile_app.variables')
                ->where('name', 'default_user_password')
                ->where('id', '<>', $passwordVariable->id)
                ->delete();

            DB::table('mobile_app.variables')
                ->where('id', $passwordVariable->id)
                ->update([
                    'club_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $clubIds = DB::table('clubs.clubs')->pluck('id');
        $globalVariables = DB::table('mobile_app.variables')
            ->whereNull('club_id')
            ->whereNull('deleted_at')
            ->get();

        foreach ($globalVariables as $variable) {
            foreach ($clubIds as $clubId) {
                DB::table('mobile_app.variables')->insertOrIgnore([
                    'name' => $variable->name,
                    'description' => $variable->description,
                    'value' => $variable->value,
                    'club_id' => $clubId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('mobile_app.variables')->whereNull('club_id')->delete();

        Schema::table('mobile_app.variables', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable(false)->change();
        });
    }
};
