<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un socio puede tener cuentas en más de un parque (mismo member_id).
 * La mayoría de los documentos son válidos para todos sus parques
 * (club_id null = documento del socio en general), pero los tipos marcados
 * como is_club_specific en catalogs.document_types (ver migración anterior)
 * se cargan una vez por parque, igual que ya se hace en
 * members.payment_sources.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members.documents', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('document_type_id')
                ->constrained('clubs.clubs');
        });
    }

    public function down(): void
    {
        Schema::table('members.documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_id');
        });
    }
};
