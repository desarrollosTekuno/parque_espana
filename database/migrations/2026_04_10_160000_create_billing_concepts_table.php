<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS billing');
        } else {
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'billing')
                BEGIN
                    EXEC('CREATE SCHEMA billing');
                END
            ");
        }

        Schema::create('billing.concepts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->boolean('allows_partial_payments')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing.concepts');
    }
};
