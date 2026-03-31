<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcement_details', function (Blueprint $table) {
            $table->renameColumn('location', 'amenity_id');
        });
        DB::statement("
            ALTER TABLE announcement_details
            ALTER COLUMN amenity_id
            TYPE BIGINT
            USING amenity_id::bigint
        ");
        Schema::table('announcement_details', function (Blueprint $table) {

            $table->foreign('amenity_id')
                ->references('id')
                ->on('amenities')
                ->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('announcement_details', function (Blueprint $table) {
            $table->dropForeign(['amenity_id']);
        });

        DB::statement("
            ALTER TABLE announcement_details
            ALTER COLUMN amenity_id
            TYPE varchar
        ");
        Schema::table('announcement_details', function (Blueprint $table) {
            $table->renameColumn('amenity_id', 'location');
        });
    }
};
