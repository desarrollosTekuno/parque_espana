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
        Schema::create('devices.daily_pass_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_no', 32);
            $table->timestamp('valid_until');
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->foreignId('device_id')->constrained('devices.devices');
            $table->foreignId('guest_user_id')->constrained('devices.guest_users');
            // Nullable: un pase puede o no venir ligado a un socio/cuenta.
            $table->foreignId('account_member_id')->nullable()->constrained('memberships.account_members')->nullOnDelete();
            // Referencia genérica al cargo/cobro que originó este pase
            // (billing.charges.id), sin importar si vino con o sin cuenta.
            $table->unsignedBigInteger('charge_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_until']);
            $table->index('card_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices.daily_pass_cards');
    }
};
