<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Quitar las foreign keys actuales de device_id/guest_user_id
        Schema::table('devices.daily_pass_cards', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropForeign(['guest_user_id']);
        });

         // 2. Hacerlas nullable.
        Schema::table('devices.daily_pass_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->change();
            $table->unsignedBigInteger('guest_user_id')->nullable()->change();
        });

        // 3. Volver a crear las foreign keys, ahora permitiendo NULL.
        Schema::table('devices.daily_pass_cards', function (Blueprint $table) {
            $table->foreign('device_id')
                ->references('id')->on('devices.devices');

            $table->foreign('guest_user_id')
                ->references('id')->on('devices.guest_users');
        });

        // 4. Agregar el nuevo estado "scheduled" al enum de status.
        //    Postgres no permite modificar un CHECK constraint de enum
        //    directamente vía Schema::table, así que se ajusta con SQL crudo.
        DB::statement("
            ALTER TABLE devices.daily_pass_cards
            DROP CONSTRAINT IF EXISTS daily_pass_cards_status_check
        ");

        DB::statement("
            ALTER TABLE devices.daily_pass_cards
            ADD CONSTRAINT daily_pass_cards_status_check
            CHECK (status IN ('scheduled', 'active', 'expired'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices.daily_pass_cards', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropForeign(['guest_user_id']);
        });

        Schema::table('devices.daily_pass_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable(false)->change();
            $table->unsignedBigInteger('guest_user_id')->nullable(false)->change();
        });

        Schema::table('devices.daily_pass_cards', function (Blueprint $table) {
            $table->foreign('device_id')
                ->references('id')->on('devices.devices');

            $table->foreign('guest_user_id')
                ->references('id')->on('devices.guest_users');
        });

        DB::statement("
            ALTER TABLE devices.daily_pass_cards
            DROP CONSTRAINT IF EXISTS daily_pass_cards_status_check
        ");

        DB::statement("
            ALTER TABLE devices.daily_pass_cards
            ADD CONSTRAINT daily_pass_cards_status_check
            CHECK (status IN ('active', 'expired'))
        ");
    }
};
