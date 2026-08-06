<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            $table->string('folio', 60)->nullable()->after('reference');
            $table->unique('folio', 'billing_payments_folio_unique');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payments', function (Blueprint $table) {
            $table->dropUnique('billing_payments_folio_unique');
            $table->dropColumn('folio');
        });
    }
};
