<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->string('rfc')->nullable()->after('address');
            $table->string('billing_url')->nullable()->after('rfc');
            $table->boolean('applies_iva')->default(false)->after('billing_url');
        });
    }

    public function down(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->dropColumn(['rfc', 'billing_url']);
            $table->dropColumn('applies_iva');
        });
    }
};
