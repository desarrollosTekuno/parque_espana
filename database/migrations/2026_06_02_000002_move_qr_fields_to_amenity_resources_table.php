<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amenities.resources', function (Blueprint $table) {
            $table->string('qr_image_path')->nullable()->after('is_active');
            $table->string('qr_url')->nullable()->after('qr_image_path');
            $table->timestamp('qr_generated_at')->nullable()->after('qr_url');
            $table->foreignId('qr_generated_by')
                ->nullable()
                ->after('qr_generated_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('amenities.locations', function (Blueprint $table) {
            $table->dropForeign(['qr_generated_by']);
            $table->dropColumn(['qr_image_path', 'qr_url', 'qr_generated_at', 'qr_generated_by']);
        });
    }

    public function down(): void
    {
        Schema::table('amenities.locations', function (Blueprint $table) {
            $table->string('qr_image_path')->nullable();
            $table->string('qr_url')->nullable();
            $table->timestamp('qr_generated_at')->nullable();
            $table->foreignId('qr_generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('amenities.resources', function (Blueprint $table) {
            $table->dropForeign(['qr_generated_by']);
            $table->dropColumn(['qr_image_path', 'qr_url', 'qr_generated_at', 'qr_generated_by']);
        });
    }
};
