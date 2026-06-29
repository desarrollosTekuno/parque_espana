<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('billing.annual_discount_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('pay_by_month');   // pagar antes de o en este mes
            $table->decimal('discount_months', 4, 2);      // 1.0 = mes completo, 0.5 = medio mes
            $table->unsignedTinyInteger('free_month');     // mes que se descuenta (12 = diciembre)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['year', 'pay_by_month']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('billing.annual_discount_rules');
    }
};
