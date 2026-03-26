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
            DB::statement('CREATE SCHEMA IF NOT EXISTS clubs');
        } else {
            // sqlserver
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'clubs')
                BEGIN
                    EXEC('CREATE SCHEMA clubs');
                END
            ");
        }

        Schema::create('clubs.clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clubs.clubs');
    }
};
