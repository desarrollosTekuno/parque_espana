<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('classes.club_closures', function (Blueprint $table) {
            $table->id();

            $table->date('date');
            $table->string('reason')->nullable();

            $table->unsignedBigInteger('club_id');
            $table->foreign('club_id')
                ->references('id')
                ->on('clubs.clubs')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['club_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.club_closures');
    }
};
