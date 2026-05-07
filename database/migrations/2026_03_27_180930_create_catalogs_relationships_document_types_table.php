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
        Schema::create('catalogs.relationships_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relationship_id')
                ->constrained('catalogs.relationships');
            $table->foreignId('document_type_id')
                ->constrained('catalogs.document_types');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs.relationships_document_types');
    }
};
