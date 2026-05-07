<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS surveys');
        } else {
            DB::statement("
                IF NOT EXISTS (SELECT * FROM sys.schemas WHERE name = 'surveys')
                BEGIN
                    EXEC('CREATE SCHEMA surveys');
                END
            ");
        }

        // Encuestas
        Schema::create('surveys.surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs.clubs');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active'])->default('draft');
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // Preguntas
        Schema::create('surveys.survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys.surveys')->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('type', ['single_choice', 'multiple_choice', 'open_text', 'rating'])->default('open_text');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->json('config')->nullable(); // para rating: {min, max, label_min, label_max}
            $table->timestamps();
        });

        // Opciones de preguntas (para single_choice y multiple_choice)
        Schema::create('surveys.survey_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('surveys.survey_questions')->cascadeOnDelete();
            $table->string('option_text');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // Tokens por usuario (un token único por usuario por encuesta)
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

        // Respuestas (una por token/usuario)
        Schema::create('surveys.survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys.surveys')->cascadeOnDelete();
            $table->foreignId('token_id')->constrained('surveys.survey_tokens')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        // Respuestas individuales por pregunta
        Schema::create('surveys.survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('surveys.survey_responses')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('surveys.survey_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();           // para open_text y rating
            $table->json('answer_options')->nullable();       // para single_choice / multiple_choice (IDs de opciones)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys.survey_answers');
        Schema::dropIfExists('surveys.survey_responses');
        Schema::dropIfExists('surveys.survey_tokens');
        Schema::dropIfExists('surveys.survey_question_options');
        Schema::dropIfExists('surveys.survey_questions');
        Schema::dropIfExists('surveys.surveys');

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP SCHEMA IF EXISTS surveys');
        } else {
            DB::statement('DROP SCHEMA surveys');
        }
    }
};
