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
            DB::statement('CREATE SCHEMA IF NOT EXISTS amenities');
        } else {
            // sqlserver
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'amenities')
                BEGIN
                    EXEC('CREATE SCHEMA amenities');
                END
            ");
        }

        Schema::create('amenities.amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // icon and background image
            $table->string('icon')->nullable();
            $table->string('background_image')->nullable();
            $table->string('description')->nullable();
            $table->string('reservation_type');
            $table->integer('capacity')->nullable();
            $table->integer('slot_duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('club_id')->constrained('clubs.clubs');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenities.amenities');
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP SCHEMA IF EXISTS amenities');
        } else {
            DB::statement('DROP SCHEMA amenities');
        }
    }
};
