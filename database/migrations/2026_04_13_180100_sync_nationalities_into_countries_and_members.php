<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('catalogs.nationalities') || !Schema::hasTable('catalogs.countries')) {
            return;
        }

        $usedNationalityIds = DB::table('members.members')
            ->whereNotNull('nationality_id')
            ->distinct()
            ->pluck('nationality_id');

        if (DB::table('catalogs.countries')->count() === 0 && $usedNationalityIds->isNotEmpty()) {
            throw new RuntimeException('catalogs.countries is empty. Import countries before switching members nationality relation.');
        }

        if ($usedNationalityIds->isNotEmpty()) {
            DB::transaction(function () use ($usedNationalityIds) {
            $nationalities = DB::table('catalogs.nationalities')
                ->select('id', 'code', 'name', 'demonym')
                ->get();

            foreach ($nationalities as $nationality) {
                DB::table('catalogs.countries')
                    ->where('iso2', $nationality->code)
                    ->update([
                        'demonym' => $nationality->demonym,
                        'updated_at' => now(),
                    ]);
            }

            $countryIdByCode = DB::table('catalogs.countries')
                ->whereNotNull('iso2')
                ->pluck('id', 'iso2');

            $nationalityCodeById = $nationalities->pluck('code', 'id');

            $missingCodes = collect($usedNationalityIds)
                ->map(fn ($id) => $nationalityCodeById[$id] ?? null)
                ->filter()
                ->reject(fn ($code) => isset($countryIdByCode[$code]))
                ->values();

            if ($missingCodes->isNotEmpty()) {
                throw new RuntimeException(
                    'Some nationalities used by members do not exist in catalogs.countries: ' . $missingCodes->implode(', ')
                );
            }

            foreach ($nationalityCodeById as $nationalityId => $code) {
                $countryId = $countryIdByCode[$code] ?? null;

                if (!$countryId) {
                    continue;
                }

                DB::table('members.members')
                    ->where('nationality_id', $nationalityId)
                    ->update(['nationality_id' => $countryId]);
            }
            });
        }

        Schema::table('members.members', function (Blueprint $table) {
            $table->dropForeign(['nationality_id']);
            $table->foreign('nationality_id')
                ->references('id')
                ->on('catalogs.countries');
        });
    }

    public function down(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            $table->dropForeign(['nationality_id']);
            $table->foreign('nationality_id')
                ->references('id')
                ->on('catalogs.nationalities');
        });
    }
};
