<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            // voluntary = baja a solicitud del titular | sanction = baja por sanción disciplinaria
            $table->string('cancellation_type')->nullable()->after('cancellation_letter_path');
        });
    }

    public function down(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->dropColumn('cancellation_type');
        });
    }
};
