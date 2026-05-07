<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback.tickets', function (Blueprint $table) {
            $table->id();

            $table->string('ticket_number', 30)->unique();

            $table->date('ticket_date');
            $table->string('title', 85);
            $table->text('description');
            $table->text('resolution_notes')->nullable();

            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->boolean('is_anonymous')->default(false);

            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('due_at')->nullable();

            $table->foreignId('club_id')
                ->constrained('clubs.clubs')
                ->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->constrained('feedback.ticket_types');

            $table->foreignId('category_id')
                ->constrained('feedback.categories');

            $table->foreignId('status_id')
                ->constrained('feedback.statuses');

            $table->foreignId('priority_id')
                ->constrained('feedback.priorities');

            $table->foreignId('member_id')
                ->nullable()
                ->constrained('members.members')
                ->nullOnDelete();

            $table->foreignId('reported_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback.tickets');
    }
};
