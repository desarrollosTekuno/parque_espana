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
        Schema::table('advertising.business_ads', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('advertising.business_categories')
                ->nullOnDelete(); // Si se elimina la categoría, se establece en null el category_id de los anuncios asociados
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advertising.business_ads', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
