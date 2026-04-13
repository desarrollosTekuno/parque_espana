<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS advertising');
        Schema::create('advertising.business_ad_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('advertising.business_ad_statuses')->insert([
            ['id' => 1, 'name' => 'pending'],
            ['id' => 2, 'name' => 'rejected'],
            ['id' => 3, 'name' => 'approved'],
            ['id' => 4, 'name' => 'paid'],
            ['id' => 5, 'name' => 'published'],
            ['id' => 6, 'name' => 'expired']
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertising.business_ad_statuses');
    }
};
