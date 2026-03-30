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
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS memberships');
        } else {
            // sqlserver
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'memberships')
                BEGIN
                    EXEC('CREATE SCHEMA memberships');
                END
            ");
        }
        Schema::create('memberships.accounts', function (Blueprint $table) {
            $table->id();

            $table->string('membership_number')->unique();

            $table->enum('account_type', [
                'individual',
                'family'
            ]);

            $table->enum('status', [
                'pending',
                'active',
                'suspended',
                'cancelled'
            ])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.accounts');
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP SCHEMA IF EXISTS memberships');
        } else {
            DB::statement('DROP SCHEMA memberships');
        }
    }
};
