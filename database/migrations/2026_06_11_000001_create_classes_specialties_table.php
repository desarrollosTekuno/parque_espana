<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('classes.specialties', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code');

            $table->unsignedBigInteger('club_id')->nullable();
            $table->foreign('club_id')->references('id')->on('clubs.clubs')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['club_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes.specialties');
    }
};
