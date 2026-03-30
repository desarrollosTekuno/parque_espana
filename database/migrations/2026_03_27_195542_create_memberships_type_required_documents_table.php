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
        Schema::create('memberships.type_required_documents', function (Blueprint $table) {
            $table->id();
            // membership type id
            $table->foreignId('membership_type_id')->constrained('memberships.types');
            // document type id
            $table->foreignId('document_type_id')->constrained('catalogs.document_types');
            // required or not
            $table->boolean('is_required')->default(true);
            // allow multiple documents of the same type
            $table->boolean('allow_multiple')->default(false);
            // files number limit
            $table->integer('number_files')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.type_required_documents');
    }
};
