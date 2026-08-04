<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los cobros de pase diario capturados desde el módulo de Cobranza quedan
 * pendientes (se pagan después, junto con el resto de la cuenta), así que el
 * pase se registra sin método de pago ni fecha de pago todavía — ver
 * DayPassController::store() y Collections/Index.vue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN payment_method_id DROP NOT NULL');
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN paid_at DROP NOT NULL');
        } else {
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN payment_method_id BIGINT NULL');
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN paid_at DATETIME NULL');
        }
    }

    public function down(): void
    {
        DB::table('guest_lists.day_passes')->whereNull('payment_method_id')->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN payment_method_id SET NOT NULL');
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN paid_at SET NOT NULL');
        } else {
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN payment_method_id BIGINT NOT NULL');
            DB::statement('ALTER TABLE guest_lists.day_passes ALTER COLUMN paid_at DATETIME NOT NULL');
        }
    }
};
