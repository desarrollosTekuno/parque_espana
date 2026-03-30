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
        Schema::create('memberships.pricing_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_type_id')
                ->constrained('memberships.types');
            // from memberships.types.id
            $table->foreignId('from_membership_type_id')
                ->nullable()
                ->constrained('memberships.types');
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();

            $table->boolean('requires_origin_family')->default(false);
            $table->boolean('requires_multiple_clubs')->default(false);

            $table->decimal('monthly_fee', 12, 2);
            $table->decimal('inscription_fee', 12, 2)->nullable();

            $table->unsignedInteger('priority')->default(10);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships.pricing_rules');
    }
};
