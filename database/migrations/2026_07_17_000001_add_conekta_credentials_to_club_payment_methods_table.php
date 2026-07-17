<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.club_payment_methods', function (Blueprint $table) {
            // Credenciales de la cuenta Conekta de este club para este método de
            // pago. Cada parque opera su propia cuenta comercial de Conekta.
            $table->string('conekta_public_key')->nullable()->after('internal_key');
            $table->text('conekta_secret_key')->nullable()->after('conekta_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('billing.club_payment_methods', function (Blueprint $table) {
            $table->dropColumn(['conekta_public_key', 'conekta_secret_key']);
        });
    }
};
