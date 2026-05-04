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
        Schema::create('members.locker_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('locker_id')
                ->constrained('members.lockers')
                ->cascadeOnDelete();

            $table->foreignId('member_id')
                ->constrained('members.members')
                ->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->decimal('amount_paid', 10, 2);

            $table->year('year');

            $table->timestamps();
            $table->softDeletes();

            // Evita que un casillero se duplique en el mismo año
            $table->unique(['locker_id', 'year']);

            // Evita múltiples casilleros por miembro en el mismo año
            $table->unique(['member_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members.locker_assignments');
    }
};
