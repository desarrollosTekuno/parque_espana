<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.club_payment_methods', function (Blueprint $table) {
            // Clave interna del método de pago específica por club. Ej: código contable, clave SAP, etc.
            $table->string('internal_key', 100)->nullable()->after('display_order');
        });
    }

    public function down(): void
    {
        Schema::table('billing.club_payment_methods', function (Blueprint $table) {
            $table->dropColumn('internal_key');
        });
    }
};
