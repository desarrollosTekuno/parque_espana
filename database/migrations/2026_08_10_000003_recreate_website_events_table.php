<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('website.events');

        Schema::create('website.events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 20);
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['club_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website.events');
    }
};
