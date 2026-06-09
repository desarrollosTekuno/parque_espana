<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->string('social_whatsapp', 200)->nullable()->after('is_active');
            $table->string('social_instagram', 500)->nullable()->after('social_whatsapp');
            $table->string('social_facebook', 500)->nullable()->after('social_instagram');
            $table->string('social_youtube', 500)->nullable()->after('social_facebook');
        });
    }

    public function down(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->dropColumn([
                'social_whatsapp',
                'social_instagram',
                'social_facebook',
                'social_youtube',
            ]);
        });
    }
};
