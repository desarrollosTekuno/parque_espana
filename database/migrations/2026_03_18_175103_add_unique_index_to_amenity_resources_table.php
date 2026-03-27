<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('amenities.resources', function (Blueprint $table) {

            $table->unique(['amenity_id', 'name', 'deleted_at'], 'amenity_resource_unique');

        });
    }

    public function down(): void
    {
        Schema::table('amenities.resources', function (Blueprint $table) {

            $table->dropUnique('amenity_resource_unique');

        });
    }
};
