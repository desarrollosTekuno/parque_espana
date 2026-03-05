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
        Schema::create('club_rules', function (Blueprint $table) {
            $table->id();
            $table->integer('max_active_reservations')->default(0);
            $table->integer('max_days_in_advance')->default(0);
            $table->boolean('allow_same_day')->default(false);
            $table->foreignId('club_id')->constrained('clubs');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_rules');
    }
};
