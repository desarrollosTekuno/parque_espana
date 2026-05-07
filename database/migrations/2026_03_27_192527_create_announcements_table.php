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
            DB::statement(
                'CREATE SCHEMA IF NOT EXISTS announcements'
            );

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
        Schema::create(
            'announcements.announcements',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')
                    ->constrained('clubs.clubs')
                    ->cascadeOnDelete();
                $table->string('title');
                $table->text('summary')
                    ->nullable();
                $table->longText('content')
                    ->nullable();
                $table->string('image')
                    ->nullable();
                $table->enum('type', [
                    'comunicado',
                    'torneo',
                    'evento',
                    'info_parque'
                ]);
                $table->boolean('is_active')
                    ->default(true);
                $table->timestamp('publish_at')
                    ->nullable();
                $table->timestamp('expires_at')
                    ->nullable();
                $table->timestamps();
                $table->softDeletes();
            }
        );
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'announcements.announcements'
        );
    }
};