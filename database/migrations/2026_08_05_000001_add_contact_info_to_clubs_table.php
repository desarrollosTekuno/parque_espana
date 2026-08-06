<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('website', 255)->nullable()->after('phone');
            $table->string('social_twitter', 500)->nullable()->after('social_youtube');
        });
    }

    public function down(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone',
                'website',
                'social_twitter',
            ]);
        });
    }
};
