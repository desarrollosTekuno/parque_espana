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
        Schema::create('memberships.account_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_account_id')
                ->constrained('memberships.accounts');

            $table->foreignId('member_id')
                ->constrained('members.members');

            $table->foreignId('relationship_id')
                ->nullable()
                ->constrained('catalogs.relationships');
            $table->boolean('is_primary_holder')->default(false);

            $table->timestamps();

            $table->unique(['membership_account_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.account_members');
    }
};
