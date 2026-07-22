<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        DB::statement('CREATE SCHEMA IF NOT EXISTS memberships');

        Schema::create('memberships.separation_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('requires_document')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('relationship_id')->nullable()->constrained('catalogs.relationships');
            $table->foreignId('document_type_id')->nullable()->constrained('catalogs.document_types');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships.separation_reasons');
    }
};
