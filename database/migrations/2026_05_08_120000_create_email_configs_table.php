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
        Schema::create('email_configs', function (Blueprint $table) {
            $table->id();
            $table->string('profile_name', 120);
            $table->string('host', 150);
            $table->unsignedSmallInteger('port');
            $table->string('username', 150);
            $table->text('password');
            $table->string('encryption', 10)->nullable();
            $table->string('from_address', 150);
            $table->string('from_name', 150);
            $table->boolean('is_active')->default(true);

            $table->unique('entity_id');
            $table->index('is_active');

            $table->unsignedBigInteger('entity_id');
            $table->foreign('entity_id')->references('id')->on('clubs.clubs')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_configs');
    }
};
