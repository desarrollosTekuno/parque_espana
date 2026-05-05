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
        Schema::create('members.fines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('act_id')->constrained('members.acts')->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('concept');
            $table->date('due_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.fines');
    }
};
