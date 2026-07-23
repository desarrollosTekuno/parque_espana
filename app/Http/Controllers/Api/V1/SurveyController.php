<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        try {
            $member = $this->getMember($request);
            if (!$member) {
                return $this->notFound('Socio no encontrado.');
            }

            if (!$this->memberBelongsToClub($member->id, $club->id)) {
                return $this->forbidden('No tienes acceso a este club.');
            }

            $answeredIds = SurveyResponse::where('member_id', $member->id)->pluck('survey_id');

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

            return $this->ok($surveys);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Error al obtener las encuestas.');
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
            return $this->notFound('Socio no encontrado.');
        }

        if ($survey->club_id !== $club->id) {
            return $this->notFound('Encuesta no encontrada.');
        }

        if ($survey->status !== 'active') {
            return $this->forbidden('Esta encuesta no está disponible.');
        }

        if (!$this->memberBelongsToClub($member->id, $club->id)) {
            return $this->forbidden('No tienes acceso a este club.');
        }

        $alreadyAnswered = SurveyResponse::where('survey_id', $survey->id)
            ->where('member_id', $member->id)
            ->exists();

        if ($alreadyAnswered) {
            return $this->conflict('Ya respondiste esta encuesta.');
        }

        $survey->load(['questions' => function ($q) {
            $q->orderBy('order')->with('options:id,question_id,option_text,order');
        }]);

        return $this->ok([
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
        ]);
    }

    /**
     * Enviar respuestas del usuario autenticado.
     * POST /api/v1/clubs/{club}/surveys/{survey}/responses
     */
    public function store(Request $request, Club $club, Survey $survey)
    {
        $member = $this->getMember($request);
        if (!$member) {
            return $this->notFound('Socio no encontrado.');
        }

        if ($survey->club_id !== $club->id || $survey->status !== 'active') {
            return $this->forbidden('Esta encuesta no está disponible.');
        }

        if (!$this->memberBelongsToClub($member->id, $club->id)) {
            return $this->forbidden('No tienes acceso a este club.');
        }

        $alreadyAnswered = SurveyResponse::where('survey_id', $survey->id)
            ->where('member_id', $member->id)
            ->exists();

        if ($alreadyAnswered) {
            return $this->conflict('Ya respondiste esta encuesta.');
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

            return $this->created('¡Gracias! Tus respuestas fueron registradas correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return $this->serverError('Error al guardar las respuestas.');
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
