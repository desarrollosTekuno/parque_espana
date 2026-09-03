<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            //
            // drop unique code
            $table->dropUnique('billing_concepts_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing.concepts', function (Blueprint $table) {
            // add unique code
            // $table->unique('code');
        });
    }
};
