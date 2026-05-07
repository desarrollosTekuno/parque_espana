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
            DB::statement('CREATE SCHEMA IF NOT EXISTS members');
        } else {
            // sqlserver
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'members')
                BEGIN
                    EXEC('CREATE SCHEMA members');
                END
            ");
        }
        Schema::create('members.members', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('second_last_name')->nullable();
            $table->date('birthdate')->nullable();
            // nationality
            $table->string('nationality')->nullable();
            // marital status
            $table->string('marital_status')->nullable();
            // phone
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            // occupation
            $table->string('occupation')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.members');
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP SCHEMA IF EXISTS members');
        } else {
            DB::statement('DROP SCHEMA members');
        }
    }
};
