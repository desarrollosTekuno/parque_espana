<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('classes.class_schedules', function (Blueprint $table) {
            $table->foreignId('specialty_id')
                ->nullable()
                ->after('amenity_resource_id')
                ->constrained('classes.specialties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes.class_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
};
