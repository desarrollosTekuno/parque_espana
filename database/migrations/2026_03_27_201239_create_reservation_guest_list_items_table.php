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
        Schema::create('reservations.guest_list_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('age');
            $table->foreignId('guest_list_id')->constrained('reservations.guest_lists');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations.guest_list_items');
    }
};
