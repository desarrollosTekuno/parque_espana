<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback.attachments', function (Blueprint $table) {
            $table->id();

            $table->string('file_name', 200);
            $table->string('file_path', 500);
            $table->string('file_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->string('storage_disk', 40)->default('public');

            $table->foreignId('ticket_id')->constrained('feedback.tickets')->cascadeOnDelete();

            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback.attachments');
    }
};
