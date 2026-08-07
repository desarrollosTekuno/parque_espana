<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website.contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('subject', 150);
            $table->text('message');
            $table->timestamps();

            $table->index(['club_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website.contact_messages');
    }
};
