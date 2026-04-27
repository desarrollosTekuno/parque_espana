<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback.comments', function (Blueprint $table) {
            $table->id();

            $table->text('comment');
            $table->boolean('is_internal')->default(false);

            $table->foreignId('ticket_id')->constrained('feedback.tickets')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback.comments');
    }
};
