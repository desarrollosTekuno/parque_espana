<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships.account_fiscal_data', function (Blueprint $table) {
            $table->id();
            $table->string('fiscal_name');
            $table->string('rfc', 20);
            $table->string('cfdi_use', 10);
            $table->string('fiscal_regime', 10);
            $table->string('postal_code', 10);
            $table->foreignId('membership_account_id')
                ->unique()
                ->constrained('memberships.accounts')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships.account_fiscal_data');
    }
};
