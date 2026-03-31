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
        Schema::create('memberships.types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Individual, Familiar, Solidaria
            $table->boolean('requires_origin_family')->default(false);
            $table->text('description')->nullable();
            // show in the front end
            $table->boolean('show_in_listing')->default(true);
            // spanish descent
            $table->boolean('is_spanish_descent')->default(false);
            // multiple members allowed (for family memberships)
            $table->boolean('allows_multiple_members')->default(false);
            $table->foreignId('club_id')
                ->constrained('clubs.clubs')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['club_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.types');
    }
};
