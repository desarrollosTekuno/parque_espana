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
        Schema::create('billing.concept_club_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained('billing.concepts')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['concept_id', 'club_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing.concept_club_amounts');
    }
};
