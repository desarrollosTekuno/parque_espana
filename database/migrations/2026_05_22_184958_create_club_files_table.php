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
        Schema::create('files.club_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreignId('club_id')->constrained('clubs.clubs')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files.files')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

        });

        DB::statement("
            CREATE UNIQUE INDEX club_files_unique_active
            ON files.club_files (club_id, file_id)
            WHERE deleted_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS files.club_files_unique_active
        ");
        Schema::dropIfExists('files.club_files');
    }
};
