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
            DB::statement('CREATE SCHEMA IF NOT EXISTS reservations');
        } else {
            // sqlserver
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'reservations')
                BEGIN
                    EXEC('CREATE SCHEMA reservations');
                END
            ");
        }

        Schema::create('reservations.reservations', function (Blueprint $table) {
            $table->id();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            // cancelled_at
            $table->timestamp('cancelled_at')->nullable();
            $table->date('reservation_date')->nullable();
            $table->foreignId('club_id')->constrained('clubs');
            $table->foreignId('amenity_id')->constrained('amenities');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations.reservations');
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP SCHEMA IF EXISTS reservations');
        } else {
            DB::statement('DROP SCHEMA reservations');
        }
    }
};
