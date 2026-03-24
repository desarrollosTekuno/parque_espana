<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('amenity_resources', function (Blueprint $table) {

            $table->id();

            $table->foreignId('amenity_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->unsignedInteger('capacity')->default(1);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_resources');
    }
};