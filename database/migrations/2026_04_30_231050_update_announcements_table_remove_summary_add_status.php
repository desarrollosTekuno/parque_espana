<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements.announcements', function (Blueprint $table) {
            // eliminar summary
            if (Schema::hasColumn('announcements', 'summary')) {
                $table->dropColumn('summary');
            }
            $table->string('status')
                ->default('draft')
                ->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->text('summary')->nullable();
            $table->dropColumn('status');
        });
    }
};