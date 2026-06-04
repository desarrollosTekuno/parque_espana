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
        Schema::table('files.files', function (Blueprint $table) {
            $table->string('module', 100)->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users');
            $table->dropUnique('files_files_code_unique');
        });

        DB::statement("
            CREATE UNIQUE INDEX files_unique_active
            ON files.files (code)
            WHERE deleted_at IS NULL
        ");

        Schema::table('files.club_files', function (Blueprint $table) {
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files.files', function (Blueprint $table) {
            $table->dropColumn('module');
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_by');
            $table->unique('code', 'files_files_code_unique');
        });

        DB::statement("
            DROP INDEX IF EXISTS files.files_unique_active
        ");

        Schema::table('files.club_files', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_by');
        });
    }
};
