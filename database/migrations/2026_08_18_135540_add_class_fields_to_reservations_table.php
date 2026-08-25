<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations.reservations', function (Blueprint $table) {
            $table->boolean('is_class')->default(false)->after('linked_reservation_id');
            $table->foreignId('coach_id')->nullable()->after('is_class')
                ->constrained('classes.coaches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations.reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coach_id');
            $table->dropColumn('is_class');
        });
    }
};
