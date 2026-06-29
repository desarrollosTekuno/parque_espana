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
        Schema::connection('pgsql')->table('amenities.amenities', function (Blueprint $table) {
            $table->string('regulation_file')->nullable()->after('background_image');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('amenities.amenities', function (Blueprint $table) {
            $table->dropColumn('regulation_file');
        });
    }
};
