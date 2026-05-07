<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memberships.pricing_rules', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->after('priority');
            $table->date('valid_until')->nullable()->after('valid_from');
            $table->boolean('is_active')->default(true)->after('valid_until');

            $table->index('priority');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships.pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['valid_from', 'valid_until', 'is_active']);
        });
    }
};
