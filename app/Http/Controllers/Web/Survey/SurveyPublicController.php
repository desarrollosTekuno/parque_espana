<?php

namespace App\Http\Controllers\Web\Survey;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\AdminClub\Survey;
use App\Models\AdminClub\SurveyToken;
use App\Models\AdminClub\SurveyResponse;
use App\Models\AdminClub\SurveyAnswer;

class SurveyPublicController extends Controller
{
    /**
     * Mostrar la encuesta pública usando el slug + token del socio.
     * URL: /encuesta/{slug}?t={token}
     */
    public function show(Request $request, string $slug)
    {
        $survey = Survey::where('slug', $slug)
            ->where('status', 'active')
            ->with(['questions.options'])
            ->firstOrFail();

        $tokenStr = $request->query('t');
        if (!$tokenStr) {
            return Inertia::render('Survey/Answer', [
                'error' => 'Enlace inválido. No se encontró el token de acceso.',
            ]);
        }

        $token = SurveyToken::where('token', $tokenStr)
            ->where('survey_id', $survey->id)
            ->first();

        if (!$token) {
            return Inertia::render('Survey/Answer', [
                'error' => 'El enlace no es válido o no corresponde a esta encuesta.',
            ]);
        }

        if ($token->used) {
            return Inertia::render('Survey/Answer', [
                'error' => 'Esta encuesta ya fue respondida con este enlace.',
                'already_answered' => true,
            ]);
        }

        return Inertia::render('Survey/Answer', [
            'survey'    => $survey,
            'tokenStr'  => $tokenStr,
        ]);
    }

    /**
     * Guardar las respuestas del socio.
     * POST /encuesta/{slug}
     */
    public function submit(Request $request, string $slug)
    {
        $survey = Survey::where('slug', $slug)
            ->where('status', 'active')
            ->with(['questions.options'])
            ->firstOrFail();

        $request->validate([
            'token'           => 'required|string',
            'answers'         => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.answer_text' => 'nullable|string',
            'answers.*.answer_options' => 'nullable|array',
        ]);

        $token = SurveyToken::where('token', $request->token)
            ->where('survey_id', $survey->id)
            ->firstOrFail();

        if ($token->used) {
            return back()->withErrors(['error' => 'Esta encuesta ya fue respondida.']);
        }

        try {
            DB::beginTransaction();

            $response = SurveyResponse::create([
                'survey_id'    => $survey->id,
                'token_id'     => $token->id,
                'submitted_at' => now(),
            ]);

            foreach ($request->answers as $ans) {
                SurveyAnswer::create([
                    'response_id'    => $response->id,
                    'question_id'    => $ans['question_id'],
                    'answer_text'    => $ans['answer_text'] ?? null,
                    'answer_options' => $ans['answer_options'] ?? null,
                ]);
            }

            $token->update([
                'used'    => true,
                'used_at' => now(),
            ]);

            DB::commit();

            return Inertia::render('Survey/Answer', [
                'submitted' => true,
                'survey'    => ['title' => $survey->title],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['error' => 'Ocurrió un error al guardar tus respuestas. Intenta de nuevo.']);
        }
    }
}
