<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('classes.coaches', function (Blueprint $table) {
            $table->foreignId('amenity_id')->nullable()->after('club_id')
                ->constrained('amenities.amenities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes.coaches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('amenity_id');
        });
    }
};
