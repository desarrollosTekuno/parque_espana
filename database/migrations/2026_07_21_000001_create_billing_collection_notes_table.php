<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create('billing.collection_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_account_id')
                ->constrained('memberships.accounts');
            $table->foreignId('club_id')
                ->nullable()
                ->constrained('clubs.clubs');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users');
            $table->text('body');
            $table->timestamps();

            $table->index('membership_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing.collection_notes');
    }
};
