<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            // Vincula los distintos Payment que se crean por un mismo cobro
            // dividido en varias formas de pago (ver
            // PaymentRegistrationService::registerSplit) — cada forma de
            // pago sigue siendo su propio registro/ticket/folio (para poder
            // cuadrar caja por instrumento), pero comparten este id para que
            // el ticket de cualquiera de ellos pueda mostrar el desglose
            // completo del cobro (ver PaymentTicketService::data).
            $table->uuid('payment_group_id')->nullable()->after('id');
            $table->index('payment_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            $table->dropIndex(['payment_group_id']);
            $table->dropColumn('payment_group_id');
        });
    }
};
