<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            $table->foreignId('birth_country_id')
                ->nullable()
                ->after('birth_place')
                ->constrained('catalogs.countries');

            $table->foreignId('birth_state_id')
                ->nullable()
                ->after('birth_country_id')
                ->constrained('catalogs.states');

            $table->foreignId('birth_city_id')
                ->nullable()
                ->after('birth_state_id')
                ->constrained('catalogs.cities');
        });

        Schema::table('members.addresses', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('postal_code')
                ->constrained('catalogs.countries');

            $table->foreignId('state_id')
                ->nullable()
                ->after('country_id')
                ->constrained('catalogs.states');

            $table->foreignId('city_id')
                ->nullable()
                ->after('state_id')
                ->constrained('catalogs.cities');
        });
    }

    public function down(): void
    {
        Schema::table('members.addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::table('members.members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('birth_city_id');
            $table->dropConstrainedForeignId('birth_state_id');
            $table->dropConstrainedForeignId('birth_country_id');
        });
    }
};
