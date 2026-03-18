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
            $table->date('reservation_date')->nullable();
            $table->foreignId('amenity_resource_id')->nullable();
            $table->foreign('amenity_resource_id')->references('id')->on('amenity_resources');
            $table->unsignedBigInteger('reservation_status_id')->nullable();
            $table->foreign('reservation_status_id')->references('id')->on('reservation_status');
            $table->dropColumn('status');
            $table->index(['amenity_resource_id', 'reservation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['amenity_resource_id']);
            $table->dropColumn('amenity_resource_id');
            $table->dropForeign(['reservation_status_id']);
            $table->dropColumn('reservation_status_id');
            $table->string('status');
            $table->dropIndex(['amenity_resource_id', 'reservation_date']);
        });
    }
};
