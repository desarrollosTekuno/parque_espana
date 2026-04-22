<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys.surveys', function (Blueprint $table) {
            $table->unsignedBigInteger('club_id')->after('id');

            $table->foreign('club_id')
                  ->references('id')
                  ->on('clubs.clubs')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('surveys.surveys', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->dropColumn('club_id');
        });
    }
};