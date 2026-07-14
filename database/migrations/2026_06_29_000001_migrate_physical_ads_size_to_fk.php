<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Agregar columnas nuevas (nullable para poder migrar datos)
        Schema::table('advertising.physical_ads', function (Blueprint $table) {
            // $table->unsignedBigInteger('physical_ad_size_id')->nullable()->after('membership_account_id');
            // $table->string('size_label', 100)->nullable()->after('physical_ad_size_id');
        });

        // 2. Poblar size_label desde el valor del enum original
        /* $sizeMap = [
            'carta'        => 'Carta',
            'oficio'       => 'Oficio',
            'doble_carta'  => 'Doble Carta',
            'doble_oficio' => 'Doble Oficio',
        ];

        foreach ($sizeMap as $enumValue => $label) {
            DB::table('advertising.physical_ads')
                ->where('size', $enumValue)
                ->update(['size_label' => $label]);
        }

        // 3. Poblar physical_ad_size_id desde el catálogo (requiere que 000003 y 000004 hayan corrido)
        $sizes = DB::table('advertising.physical_ad_sizes')
            ->select('id', 'club_id', 'label')
            ->get();

        foreach ($sizes as $size) {
            DB::table('advertising.physical_ads')
                ->where('club_id', $size->club_id)
                ->where('size_label', $size->label)
                ->update(['physical_ad_size_id' => $size->id]);
        }

        // 4. Eliminar la columna original
        Schema::table('advertising.physical_ads', function (Blueprint $table) {
            $table->dropColumn('size');
        }); */
    }

    public function down(): void
    {
        /* Schema::table('advertising.physical_ads', function (Blueprint $table) {
            $table->string('size', 50)->nullable()->after('membership_account_id');
        });

        // Restaurar valor del enum desde size_label
        $reverseMap = [
            'Carta'        => 'carta',
            'Oficio'       => 'oficio',
            'Doble Carta'  => 'doble_carta',
            'Doble Oficio' => 'doble_oficio',
        ];

        foreach ($reverseMap as $label => $enumValue) {
            DB::table('advertising.physical_ads')
                ->where('size_label', $label)
                ->update(['size' => $enumValue]);
        }

        Schema::table('advertising.physical_ads', function (Blueprint $table) {
            $table->dropColumn(['physical_ad_size_id', 'size_label']);
        }); */
    }
};
