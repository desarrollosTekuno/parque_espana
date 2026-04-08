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
        Schema::table('memberships.types', function (Blueprint $table) {
            //
            $table->unsignedSmallInteger('validity_months')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships.types', function (Blueprint $table) {
            //
            $table->dropColumn('validity_months');
        });
    }
};
