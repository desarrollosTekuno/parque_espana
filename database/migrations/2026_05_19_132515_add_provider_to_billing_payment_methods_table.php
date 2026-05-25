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
        Schema::table('billing.payment_methods', function (Blueprint $table) {
            // Proveedor de pago externo. Ej: 'conekta'. Null = método interno.
            $table->string('provider', 50)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('billing.payment_methods', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
