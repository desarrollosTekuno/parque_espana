<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website.virtual_tour_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'name']);
        });

        Schema::create('website.virtual_tour_images', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('image_path');
            $table->foreignId('category_id')
                ->constrained('website.virtual_tour_categories')
                ->cascadeOnDelete();
            $table->timestamps();
        });

        $categories = ['Interior', 'Exterior', 'Servicios', 'Actividad física', 'Estacionamiento'];

        foreach (DB::table('clubs.clubs')->pluck('id') as $clubId) {
            foreach ($categories as $category) {
                DB::table('website.virtual_tour_categories')->insert([
                    'club_id' => $clubId,
                    'name' => $category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website.virtual_tour_images');
        Schema::dropIfExists('website.virtual_tour_categories');
    }
};
