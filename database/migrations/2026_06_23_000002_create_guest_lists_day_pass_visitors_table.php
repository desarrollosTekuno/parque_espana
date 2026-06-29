<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_lists.day_pass_visitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('day_pass_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->integer('age');
            $table->decimal('price', 12, 2);
            $table->string('ticket_code')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_lists.day_pass_visitors');
    }
};
