<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations.reservations', function (Blueprint $table) {
            $table->boolean('requires_tent')->nullable()->after('reservation_date');
            $table->unsignedInteger('tables_count')->nullable()->after('requires_tent');
            $table->unsignedInteger('chairs_count')->nullable()->after('tables_count');
            $table->text('notes')->nullable()->after('chairs_count');
            $table->foreignId('linked_reservation_id')->nullable()->after('notes')
                ->constrained('reservations.reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations.reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_reservation_id');
            $table->dropColumn([
                'requires_tent',
                'tables_count',
                'chairs_count',
                'notes',
            ]);
        });
    }
};
