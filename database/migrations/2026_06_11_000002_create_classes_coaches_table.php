<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.coaches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('club_id');
            $table->foreign('club_id')
                ->references('id')
                ->on('clubs.clubs')
                ->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('second_last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('photo')->nullable();
            $table->json('specialties')->default('[]');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.coaches');
    }
};
