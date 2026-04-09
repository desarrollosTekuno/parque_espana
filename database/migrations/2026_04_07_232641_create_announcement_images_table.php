<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS announcements');
        } else {
            DB::statement("
                IF NOT EXISTS (
                    SELECT *
                    FROM sys.schemas
                    WHERE name = 'announcements'
                )
                BEGIN
                    EXEC('CREATE SCHEMA announcements');
                END
            ");
        }
        
        Schema::create('announcements.images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')
                  ->constrained('announcements.announcements')
                  ->cascadeOnDelete();
            $table->string('image');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements.images');
    }
};
