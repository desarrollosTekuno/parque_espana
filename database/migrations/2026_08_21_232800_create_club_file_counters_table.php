<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files.club_file_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('current_value')->default(0);
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files.files')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'file_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files.club_file_counters');
    }
};
