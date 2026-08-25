<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            // 'cancelled' ya estaba permitido en el CHECK de status, pero
            // nunca se usaba — se agrega el mismo rastro que ya tiene
            // billing.charges (cancelled_at/cancelled_by/cancellation_reason)
            // para poder cancelar un pago (p. ej. cheque rebotado) dejando
            // registro de quién y por qué.
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->after('cancelled_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
