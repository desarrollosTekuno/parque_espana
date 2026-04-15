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
            ['id' => 1, 'name' => 'Pendiente'],
            ['id' => 2, 'name' => 'Rechazado'],
            ['id' => 3, 'name' => 'Aprobado'],
            ['id' => 4, 'name' => 'Pagado'],
            ['id' => 5, 'name' => 'Publicado'],
            ['id' => 6, 'name' => 'Expirado']
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
