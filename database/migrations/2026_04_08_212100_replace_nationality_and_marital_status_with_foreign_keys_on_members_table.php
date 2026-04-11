<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            $table->foreignId('nationality_id')
                ->nullable()
                ->after('city')
                ->constrained('catalogs.nationalities');

            $table->foreignId('marital_status_id')
                ->nullable()
                ->after('nationality_id')
                ->constrained('catalogs.marital_statuses');
        });

        Schema::table('members.members', function (Blueprint $table) {
            $table->dropColumn(['nationality', 'marital_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members.members', function (Blueprint $table) {
            $table->string('nationality')->nullable()->after('city');
            $table->string('marital_status')->nullable()->after('nationality');
        });

        Schema::table('members.members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marital_status_id');
            $table->dropConstrainedForeignId('nationality_id');
        });
    }
};
