<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities.attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('amenity_resource_location_id')
                ->constrained('amenities.locations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('club_id');

            $table->timestamp('checked_in_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenities.attendances');
    }
};
