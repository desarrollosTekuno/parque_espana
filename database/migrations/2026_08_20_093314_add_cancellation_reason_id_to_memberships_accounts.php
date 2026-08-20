<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->foreignId('cancellation_reason_id')
                ->nullable()
                ->after('cancellation_type')
                ->constrained('catalogs.cancellation_reasons')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memberships.accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_reason_id');
        });
    }
};
