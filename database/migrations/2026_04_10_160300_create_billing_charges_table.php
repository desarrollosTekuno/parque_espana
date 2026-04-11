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
        Schema::create('billing.charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_account_id')
                ->constrained('memberships.accounts');
            $table->foreignId('membership_id')
                ->nullable()
                ->constrained('memberships.memberships');
            $table->foreignId('member_id')
                ->nullable()
                ->constrained('members.members');
            $table->foreignId('concept_id')
                ->constrained('billing.concepts');
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->boolean('allows_partial_payments')->default(false);
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing.charges');
    }
};
