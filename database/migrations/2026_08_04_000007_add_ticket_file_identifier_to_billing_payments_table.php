<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            $table->string('ticket_file_identifier', 40)->nullable()->after('folio');
            $table->unique('ticket_file_identifier', 'billing_payments_ticket_file_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            $table->dropUnique('billing_payments_ticket_file_identifier_unique');
            $table->dropColumn('ticket_file_identifier');
        });
    }
};
