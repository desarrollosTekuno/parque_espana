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
            DB::statement('CREATE SCHEMA IF NOT EXISTS website');
        } else {
            DB::statement("IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'website') EXEC('CREATE SCHEMA website')");
        }

        Schema::create('website.carousel_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('description', 100)->nullable()->after('image_path');

            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website.carousel_images');
    }
};
