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
        Schema::table('amenities', function (Blueprint $table) {
            if (Schema::hasColumn('amenities', 'slot_duration_minutes')) {
                $table->dropColumn('slot_duration_minutes');
            }
        });

        Schema::table('amenity_resources', function (Blueprint $table) {
            if (!Schema::hasColumn('amenity_resources', 'slot_duration_minutes')) {
                $table->integer('slot_duration_minutes')
                      ->nullable()
                      ->after('capacity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            if (!Schema::hasColumn('amenities', 'slot_duration_minutes')) {
                $table->integer('slot_duration_minutes')
                      ->nullable()
                      ->after('reservation_type');
            }
        });

        Schema::table('amenity_resources', function (Blueprint $table) {
            if (Schema::hasColumn('amenity_resources', 'slot_duration_minutes')) {
                $table->dropColumn('slot_duration_minutes');
            }
        });
    }
};
