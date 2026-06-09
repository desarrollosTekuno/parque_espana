<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta el tipo de documento de sistema "Comprobante de descuento de inscripción".
 * Se usa cuando el operador aplica un descuento en la cuota de inscripción al dar
 * de alta una membresía. El documento queda en el expediente del titular.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('catalogs.document_types')->insertOrIgnore([
            'code'               => 'INSCRIPTION_DISCOUNT',
            'name'               => 'Comprobante de descuento de inscripción',
            'description'        => 'Documento de respaldo que justifica el descuento aplicado sobre la cuota de inscripción.',
            'allowed_extensions' => 'pdf,jpg,jpeg,png',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('catalogs.document_types')
            ->where('code', 'INSCRIPTION_DISCOUNT')
            ->delete();
    }
};
