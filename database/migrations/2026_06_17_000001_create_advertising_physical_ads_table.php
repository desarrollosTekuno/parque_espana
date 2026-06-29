<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('advertising.physical_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->foreignId('member_id')->constrained('members.members');
            $table->foreignId('membership_account_id')->constrained('memberships.accounts');
            $table->enum('size', ['carta', 'oficio', 'doble_carta', 'doble_oficio']);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('amount', 12, 2);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('signed_format')->default(false);
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.physical_ads');
    }
};
