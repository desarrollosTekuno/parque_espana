<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advertising.business_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')
                ->constrained('clubs.clubs') 
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertising.business_categories');
    }
};
