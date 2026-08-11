<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            // Si el concepto factura IVA por default (cuando no hay un
            // override específico para el parque en billing.concept_club_amounts).
            // Reemplaza el criterio anterior de usar clubs.clubs.applies_iva a
            // nivel global: ahora se decide por concepto (y, si aplica, por
            // parque), no por parque nada más.
            $table->boolean('applies_iva')->default(false)->after('splits_between_parks');
        });

        Schema::table('billing.concept_club_amounts', function (Blueprint $table) {
            // El monto por parque ya podía dejarse vacío (usa el monto base);
            // ahora el renglón también puede existir solo para overridear si
            // aplica IVA en ese parque, sin necesariamente overridear el
            // monto — por eso amount pasa a ser opcional.
            $table->decimal('amount', 12, 2)->nullable()->change();

            // null = no hay override para este parque, se usa
            // billing.concepts.applies_iva del concepto.
            $table->boolean('applies_iva')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('billing.concept_club_amounts', function (Blueprint $table) {
            $table->dropColumn('applies_iva');
            $table->decimal('amount', 12, 2)->nullable(false)->change();
        });

        Schema::table('billing.concepts', function (Blueprint $table) {
            $table->dropColumn('applies_iva');
        });
    }
};
