<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS files');
        } else {
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'files')
                BEGIN
                    EXEC('CREATE SCHEMA files');
                END
            ");
        }

        Schema::create('files.files', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('allowed_mime_types')->nullable();
            $table->unsignedBigInteger('max_size_bytes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files.files');
    }
};
