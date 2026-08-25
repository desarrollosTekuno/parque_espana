<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->string('social_threads', 500)
                ->nullable()
                ->after('social_youtube');
        });
    }

    public function down(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->dropColumn('social_threads');
        });
    }
};