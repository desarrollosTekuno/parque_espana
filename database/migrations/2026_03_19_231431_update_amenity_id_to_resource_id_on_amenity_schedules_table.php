<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amenity_schedules', function (Blueprint $table) {
            $table->dropForeign('amenity_schedules_amenity_id_foreign');
            $table->renameColumn('amenity_id', 'resource_id');
        });

        Schema::table('amenity_schedules', function (Blueprint $table) {
            $table->foreign('resource_id')
                ->references('id')
                ->on('amenity_resources')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('amenity_schedules', function (Blueprint $table) {
            $table->dropForeign(['resource_id']);
            $table->renameColumn('resource_id', 'amenity_id');
            $table->foreign('amenity_id')
                ->references('id')
                ->on('amenities')
                ->onDelete('cascade');
        });
    }
};