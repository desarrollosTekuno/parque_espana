<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website.home_cards', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30);
            $table->string('image_path');

            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['club_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website.home_cards');
    }
};
