<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\AdminClub\Survey;
use App\Models\AdminClub\SurveyResponse;
use App\Models\AdminClub\SurveyAnswer;
use App\Models\Administrator\Club;
use App\Models\Members\Member;
use App\Rules\ExistsInSchema;

class SurveyController extends Controller
{
    /**
     * Encuestas activas del club que el usuario autenticado aún no ha respondido.
     * GET /api/v1/clubs/{club}/surveys
     */
    public function index(Request $request, Club $club)
    {
        try{
            $member = $this->getMember($request);
        if (!$member) {
            return response()->json(['message' => 'Socio no encontrado.'], 404);
        }

        if (!$this->memberBelongsToClub($member->id, $club->id)) {
            return response()->json(['message' => 'No tienes acceso a este club.'], 403);
        }

        $answeredIds = SurveyResponse::where('member_id', $member->id)
            ->pluck('survey_id');

        $surveys = Survey::where('club_id', $club->id)
            ->where('status', 'active')
            ->whereNotIn('id', $answeredIds)
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id'              => $s->id,
                'title'           => $s->title,
                'description'     => $s->description,
                'questions_count' => $s->questions_count,
            ]);

        return response()->json(['data' => $surveys]);

        } catch (\Exception $e) {
            report($e);
            return response()->json(['message' => "Error al obtener las encuestas. {$e->getMessage()}"], 500);
        }
    }

    /**
     * Detalle de una encuesta con preguntas y opciones.
     * GET /api/v1/clubs/{club}/surveys/{survey}
     */
    public function show(Request $request, Club $club, Survey $survey)
    {
        $member = $this->getMember($request);
        if (!$member) {
            return response()->json(['message' => 'Socio no encontrado.'], 404);
        }

        if ($survey->club_id !== $club->id) {
            return response()->json(['message' => 'Encuesta no encontrada.'], 404);
        }

        if ($survey->status !== 'active') {
            return response()->json(['message' => 'Esta encuesta no está disponible.'], 403);
        }

        if (!$this->memberBelongsToClub($member->id, $club->id)) {
            return response()->json(['message' => 'No tienes acceso a este club.'], 403);
        }

        $alreadyAnswered = SurveyResponse::where('survey_id', $survey->id)
            ->where('member_id', $member->id)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'Ya respondiste esta encuesta.'], 409);
        }

        $survey->load(['questions' => function ($q) {
            $q->orderBy('order')->with('options:id,question_id,option_text,order');
        }]);

        return response()->json([
            'data' => [
                'id'          => $survey->id,
                'title'       => $survey->title,
                'description' => $survey->description,
                'questions'   => $survey->questions->map(fn ($q) => [
                    'id'          => $q->id,
                    'text'        => $q->question_text,
                    'type'        => $q->type,
                    'is_required' => $q->is_required,
                    'config'      => $q->config,
                    'options'     => $q->options->map(fn ($o) => [
                        'id'   => $o->id,
                        'text' => $o->option_text,
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Enviar respuestas del usuario autenticado.
     * POST /api/v1/clubs/{club}/surveys/{survey}/responses
     *
     * Body:
     * {
     *   "answers": [
     *     { "question_id": 1, "answer_text": null,   "answer_options": [3] },
     *     { "question_id": 2, "answer_text": "Texto", "answer_options": [] },
     *     { "question_id": 3, "answer_text": "4",     "answer_options": [] }
     *   ]
     * }
     */
    public function store(Request $request, Club $club, Survey $survey)
    {
        $member = $this->getMember($request);
        if (!$member) {
            return response()->json(['message' => 'Socio no encontrado.'], 404);
        }

        if ($survey->club_id !== $club->id || $survey->status !== 'active') {
            return response()->json(['message' => 'Esta encuesta no está disponible.'], 403);
        }

        if (!$this->memberBelongsToClub($member->id, $club->id)) {
            return response()->json(['message' => 'No tienes acceso a este club.'], 403);
        }

        $alreadyAnswered = SurveyResponse::where('survey_id', $survey->id)
            ->where('member_id', $member->id)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'Ya respondiste esta encuesta.'], 409);
        }

        $request->validate([
            'answers'                    => 'required|array',
            'answers.*.question_id'      => ['required', 'integer', new ExistsInSchema('surveys', 'survey_questions', 'id')],
            'answers.*.answer_text'      => 'nullable|string',
            'answers.*.answer_options'   => 'nullable|array',
            'answers.*.answer_options.*' => 'integer',
        ]);

        try {
            DB::beginTransaction();

            $response = SurveyResponse::create([
                'survey_id'    => $survey->id,
                'member_id'    => $member->id,
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

            DB::commit();

            return response()->json([
                'message' => '¡Gracias! Tus respuestas fueron registradas correctamente.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Error al guardar las respuestas.'], 500);
        }
    }

    // ─────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────

    private function getMember(Request $request): ?Member
    {
        return Member::where('user_id', $request->user()->id)->first();
    }

    private function memberBelongsToClub(int $memberId, int $clubId): bool
    {
        return DB::table('memberships.account_members')
            ->join('memberships.accounts', 'memberships.accounts.id', '=', 'memberships.account_members.membership_account_id')
            ->where('memberships.account_members.member_id', $memberId)
            ->where('memberships.accounts.club_id', $clubId)
            ->exists();
    }
}
