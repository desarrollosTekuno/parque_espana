<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS devices');
        } else {
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'devices')
                BEGIN
                    EXEC('CREATE SCHEMA devices');
                END
            ");
        }

        Schema::create('devices.devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('ip', 45);
            $table->string('status')->default('active'); // active, inactive, maintenance
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // unique per club, not globally
            $table->unique(['club_id', 'ip']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices.devices');
    }
};
