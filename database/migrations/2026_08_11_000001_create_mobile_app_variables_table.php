<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS mobile_app');
        } else {
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'mobile_app')
                BEGIN
                    EXEC('CREATE SCHEMA mobile_app');
                END
            ");
        }

        Schema::create('mobile_app.variables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('description')->nullable();
            $table->string('value', 100)->nullable();
            $table->foreignId('club_id')->constrained('clubs.clubs');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app.variables');
    }
};
