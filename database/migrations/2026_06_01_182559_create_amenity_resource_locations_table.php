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
        Schema::create('amenities.locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('amenity_resource_id')
                ->constrained('amenities.resources')
                ->cascadeOnDelete();

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);

            $table->string('qr_image_path')->nullable();

            $table->string('qr_url')->nullable();

            $table->timestamp('qr_generated_at')->nullable();

            $table->foreignId('qr_generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenities.locations');
    }
};
