<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('members.documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')
                ->constrained('members.members');
            $table->foreignId('document_type_id')
                ->constrained('catalogs.document_types');
            $table->string('file_path');
            $table->boolean('is_verified')->default(false);
            // verified by
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users');
            // verified at
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.documents');
    }
};
