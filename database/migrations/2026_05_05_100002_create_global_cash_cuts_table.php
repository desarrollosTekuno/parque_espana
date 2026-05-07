<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing.global_cash_cuts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->date('date');
            $table->string('status', 20)->default('open'); // open, closed
            $table->unsignedBigInteger('created_by');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['club_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing.global_cash_cuts');
    }
};
