<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('rfc', 20)->nullable()->after('address');
            $table->string('billing_url')->nullable()->after('rfc');
            $table->boolean('applies_iva')->default(false)->after('billing_url');
        });
    }

    public function down(): void
    {
        Schema::table('clubs.clubs', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'rfc',
                'billing_url',
                'applies_iva',
            ]);
        });
    }
};
