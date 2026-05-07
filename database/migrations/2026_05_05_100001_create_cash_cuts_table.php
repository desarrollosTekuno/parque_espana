<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing.cash_cuts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->decimal('opening_amount', 10, 2)->default(0);
            $table->string('status', 20)->default('open'); // open, closed
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('cash_counted', 10, 2)->nullable();
            $table->decimal('cash_expected', 10, 2)->nullable();
            $table->decimal('cash_difference', 10, 2)->nullable();
            $table->unsignedBigInteger('global_cash_cut_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'user_id', 'date']);
        });

        Schema::create('billing.cash_cut_denominations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cash_cut_id');
            $table->decimal('denomination', 10, 2);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['cash_cut_id', 'denomination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing.cash_cut_denominations');
        Schema::dropIfExists('billing.cash_cuts');
    }
};
