<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasSizeColumn = Schema::hasColumn('advertising.physical_ads', 'size');
        $hasSizeIdColumn = Schema::hasColumn('advertising.physical_ads', 'physical_ad_size_id');
        $hasSizeLabelColumn = Schema::hasColumn('advertising.physical_ads', 'size_label');

        // 1. Agregar columnas nuevas si no existen ya (la tabla puede haberse
        // creado directamente con esta estructura en ambientes nuevos).
        if (!$hasSizeIdColumn || !$hasSizeLabelColumn) {
            Schema::table('advertising.physical_ads', function (Blueprint $table) use ($hasSizeIdColumn, $hasSizeLabelColumn) {
                if (!$hasSizeIdColumn) {
                    $table->unsignedBigInteger('physical_ad_size_id')->nullable()->after('membership_account_id');
                }
                if (!$hasSizeLabelColumn) {
                    $table->string('size_label', 100)->nullable()->after('physical_ad_size_id');
                }
            });
        }

        if (!$hasSizeColumn) {
            return;
        }

        // 2. Poblar size_label desde el valor del enum original
        $sizeMap = [
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
        });
    }

    public function down(): void
    {
        Schema::table('advertising.physical_ads', function (Blueprint $table) {
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
        });
    }
};
