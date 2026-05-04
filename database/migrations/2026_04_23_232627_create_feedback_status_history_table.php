<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback.status_history', function (Blueprint $table) {
            $table->id();
            $table->text('change_reason')->nullable();

            $table->foreignId('ticket_id')->constrained('feedback.tickets')->cascadeOnDelete();

            $table->foreignId('old_status_id')->nullable()->constrained('feedback.statuses')->nullOnDelete();

            $table->foreignId('new_status_id')->constrained('feedback.statuses');

            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback.status_history');
    }
};
