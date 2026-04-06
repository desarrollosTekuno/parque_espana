<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amenities.blocked_periods', function (Blueprint $table) {
            $table->dropForeign(['amenity_id']);
            $table->dropColumn('amenity_id');
            $table->foreignId('resource_id')
                ->after('reason')
                ->constrained('amenities.resources')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('amenities.blocked_periods', function (Blueprint $table) {
            $table->dropForeign(['resource_id']);
            $table->dropColumn('resource_id');
            $table->foreignId('amenity_id')
                ->constrained('amenities.amenities')
                ->cascadeOnDelete();

        });
    }
};