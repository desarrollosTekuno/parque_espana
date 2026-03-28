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
        Schema::create('reservations.guest_lists', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->integer('total_guests');
            $table->integer('total_adults');
            $table->integer('total_children');
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('discount', 12, 2)->nullable();
            $table->decimal('total', 12, 2);
            $table->date('approved_at')->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('reservation_id')->constrained('reservations.reservations');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations.guest_lists');
    }
};
