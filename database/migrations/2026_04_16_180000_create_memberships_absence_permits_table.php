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
        Schema::create('memberships.absence_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_group_id')
                ->constrained('memberships.account_groups');
            $table->foreignId('membership_account_id')
                ->nullable()
                ->constrained('memberships.accounts');
            $table->foreignId('primary_member_id')
                ->constrained('members.members');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('charge_percentage', 5, 2)->default(25.00);
            $table->enum('status', ['pending', 'approved', 'active', 'finished', 'cancelled'])
                ->default('approved');
            $table->boolean('blocks_facility_access')->default(true);
            $table->boolean('blocks_reservations')->default(true);
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.absence_permits');
    }
};
