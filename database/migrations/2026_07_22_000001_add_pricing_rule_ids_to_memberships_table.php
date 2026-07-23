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
        Schema::table('memberships.memberships', function (Blueprint $table) {
            $table->foreignId('pricing_rule_id')
                ->nullable()
                ->after('membership_type_id')
                ->constrained('memberships.pricing_rules')
                ->nullOnDelete();

            $table->foreignId('interclub_package_rule_id')
                ->nullable()
                ->after('pricing_rule_id')
                ->constrained('memberships.interclub_package_rules')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships.memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_rule_id');
            $table->dropConstrainedForeignId('interclub_package_rule_id');
        });
    }
};
