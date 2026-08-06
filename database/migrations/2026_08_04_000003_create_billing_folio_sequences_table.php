<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS billing');
        } else {
            DB::statement("IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'billing') EXEC('CREATE SCHEMA billing')");
        }

        Schema::create('billing.folio_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')
                ->constrained('clubs.clubs');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users');
            $table->string('series', 20);
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(
                ['club_id', 'series', 'sequence_date'],
                'folio_sequences_club_series_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing.folio_sequences');
    }
};
