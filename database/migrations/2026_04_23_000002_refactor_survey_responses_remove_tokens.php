<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Primero quitar el FK y columna en survey_responses (que referencia survey_tokens)
        Schema::table('surveys.survey_responses', function (Blueprint $table) {
            $table->dropForeign(['token_id']);
            $table->dropColumn('token_id');

            $table->foreignId('member_id')
                  ->after('survey_id')
                  ->constrained('members.members');

            $table->unique(['survey_id', 'member_id']);
        });

        // 2. Ahora sí se puede eliminar survey_tokens sin dependencias
        Schema::dropIfExists('surveys.survey_tokens');
    }

    public function down(): void
    {
        // 1. Recrear survey_tokens antes de agregar el FK que la referencia
        Schema::create('surveys.survey_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys.surveys')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members.members');
            $table->string('token', 64)->unique();
            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->unique(['survey_id', 'member_id']);
        });

        // 2. Ahora se puede revertir survey_responses y agregar el FK a survey_tokens
        Schema::table('surveys.survey_responses', function (Blueprint $table) {
            $table->dropUnique(['survey_id', 'member_id']);
            $table->dropForeign(['member_id']);
            $table->dropColumn('member_id');

            /* $table->foreignId('token_id')
                  ->constrained('surveys.survey_tokens')
                  ->cascadeOnDelete(); */
        });
    }
};
