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
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_status_id')->nullable();
            $table->foreign('reservation_status_id')->references('id')->on('reservation_status');
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['reservation_status_id']);
            $table->dropColumn('reservation_status_id');
            $table->string('status');
        });
    }
};
