<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members.conekta_customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('club_id');

            // Customer id de Conekta (cus_xxx), único dentro de la cuenta del club.
            $table->string('conekta_customer_id');

            $table->timestamps();

            $table->foreign('club_id')
                ->references('id')->on('clubs.clubs');

            $table->unique(['member_id', 'club_id']);
            $table->unique('conekta_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members.conekta_customers');
    }
};
