<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {

            if (!Schema::hasColumn('reservations', 'amenity_resource_id')) {

                $table->foreignId('amenity_resource_id')->nullable();

                $table->foreign('amenity_resource_id')
                    ->references('id')
                    ->on('amenity_resources');

            }

        });

        DB::statement("
            CREATE INDEX IF NOT EXISTS amenity_resource_id_date_index
            ON reservations (amenity_resource_id, date)
        ");
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {

            if (Schema::hasColumn('reservations', 'amenity_resource_id')) {

                $table->dropForeign(['amenity_resource_id']);
                $table->dropColumn('amenity_resource_id');

            }

        });

        DB::statement("
            DROP INDEX IF EXISTS amenity_resource_id_date_index
        ");
    }
};