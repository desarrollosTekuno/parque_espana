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
        Schema::create('members.warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained('members.acts')->cascadeOnDelete();

            $table->string('type');

            $table->boolean('has_suspension')->default(false);
            $table->date('suspension_start')->nullable();
            $table->date('suspension_end')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.warnings');
    }
};
