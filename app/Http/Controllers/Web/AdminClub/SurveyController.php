<?php

namespace App\Http\Controllers\Web\AdminClub;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\AdminClub\Survey;
use App\Models\AdminClub\SurveyQuestion;
use App\Models\AdminClub\SurveyQuestionOption;
use App\Rules\ExistsInSchema;

class SurveyController extends Controller
{
    // ─────────────────────────────────────────
    //  SURVEYS
    // ─────────────────────────────────────────
    public function __construct()
    {
        $this->middleware('can:surveys.index')->only('index');
        $this->middleware('can:surveys.create')->only('create');
        $this->middleware('can:surveys.store')->only('store');
        $this->middleware('can:surveys.edit')->only('edit');
        $this->middleware('can:surveys.update')->only('update');
        $this->middleware('can:surveys.destroy')->only('destroy');
        $this->middleware('can:surveys.questions.store')->only('storeQuestion');
        $this->middleware('can:surveys.questions.update')->only('updateQuestion');
        $this->middleware('can:surveys.questions.destroy')->only('destroyQuestion');
        $this->middleware('can:surveys.questions.reorder')->only('reorderQuestions');
    }

     /**
     * Listado de encuestas del club.
     * GET /admin/clubs/{club}/surveys
     */

    public function index(Request $request)
    {
        try {
            $clubId = session('club_id');
            $driver = DB::getDriverName();
            $prefix = 'surveys';

            $query = Survey::forClub($clubId);

            if ($search = $request->input("{$prefix}_search")) {
                $op = $driver === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($q) use ($search, $op) {
                    $q->where('title', $op, "%{$search}%")
                      ->orWhere('description', $op, "%{$search}%");
                });
            }

            if ($status = $request->input("{$prefix}_status")) {
                $query->where('status', $status);
            }

            $sort  = $request->input("{$prefix}_sort", 'id');
            $order = $request->input("{$prefix}_order", 'desc');
            $query->orderBy($sort, $order);

            $surveys = $query
                ->withCount('questions')
                ->withCount('responses')
                ->paginate($request->input("{$prefix}_per_page", 10))
                ->appends($request->except('club_id'));

            return Inertia::render('AdminClubs/Surveys/Index', [
                'surveys' => $surveys,
            ]);
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('AdminClubs/Surveys/Index', [
                'surveys'      => ['data' => [], 'total' => 0],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function create()
    {
        return Inertia::render('AdminClubs/Surveys/Edit', [
            'survey' => null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:draft,active',
        ]);

        try {
            DB::beginTransaction();

            $survey = Survey::create([
                'club_id'     => session('club_id'),
                'title'       => $request->title,
                'description' => $request->description,
                'status'      => $request->status,
                'slug'        => $this->generateUniqueSlug(),
            ]);

            DB::commit();

            return redirect()
                ->route('surveys.edit', $survey)
                ->with('success', 'Encuesta creada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['messageError' => $e->getMessage()])->withInput();
        }
    }

    public function edit(Survey $survey)
    {
        $survey->load(['questions.options']);

        return Inertia::render('AdminClubs/Surveys/Edit', [
            'survey' => $survey,
        ]);
    }

    public function update(Request $request, Survey $survey)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:draft,active',
        ]);

        try {
            DB::beginTransaction();

            $survey->update([
                'title'       => $request->title,
                'description' => $request->description,
                'status'      => $request->status,
            ]);

            DB::commit();

            return back()->with('success', 'Encuesta actualizada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['messageError' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Survey $survey)
    {
        try {
            DB::beginTransaction();
            $survey->delete();
            DB::commit();

            return redirect()
                ->route('surveys.index')
                ->with('success', 'Encuesta eliminada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    // ─────────────────────────────────────────
    //  QUESTIONS
    // ─────────────────────────────────────────

    public function storeQuestion(Request $request, Survey $survey)
    {
        $request->validate([
            'question_text'         => 'required|string',
            'type'                  => 'required|in:single_choice,multiple_choice,open_text,rating',
            'is_required'           => 'boolean',
            'config'                => 'nullable|array',
            'options'               => 'nullable|array',
            'options.*.option_text' => 'required_if:type,single_choice|required_if:type,multiple_choice|string',
        ]);

        try {
            DB::beginTransaction();

            $maxOrder = $survey->questions()->max('order') ?? -1;

            $question = SurveyQuestion::create([
                'survey_id'     => $survey->id,
                'question_text' => $request->question_text,
                'type'          => $request->type,
                'order'         => $maxOrder + 1,
                'is_required'   => $request->boolean('is_required', true),
                'config'        => $request->config,
            ]);

            if (in_array($request->type, ['single_choice', 'multiple_choice']) && $request->options) {
                foreach ($request->options as $idx => $opt) {
                    SurveyQuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $opt['option_text'],
                        'order'       => $idx,
                    ]);
                }
            }

            DB::commit();
            return back()->with('success', 'Pregunta agregada');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function updateQuestion(Request $request, Survey $survey, SurveyQuestion $question)
    {
        $request->validate([
            'question_text'         => 'required|string',
            'type'                  => 'required|in:single_choice,multiple_choice,open_text,rating',
            'is_required'           => 'boolean',
            'config'                => 'nullable|array',
            'options'               => 'nullable|array',
            'options.*.option_text' => 'string',
        ]);

        try {
            DB::beginTransaction();

            $question->update([
                'question_text' => $request->question_text,
                'type'          => $request->type,
                'is_required'   => $request->boolean('is_required', true),
                'config'        => $request->config,
            ]);

            // Reemplazar opciones si aplica
            $question->options()->delete();
            if (in_array($request->type, ['single_choice', 'multiple_choice']) && $request->options) {
                foreach ($request->options as $idx => $opt) {
                    SurveyQuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $opt['option_text'],
                        'order'       => $idx,
                    ]);
                }
            }

            DB::commit();
            return back()->with('success', 'Pregunta actualizada');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function destroyQuestion(Survey $survey, SurveyQuestion $question)
    {
        try {
            DB::beginTransaction();
            $question->options()->delete();
            $question->delete();
            DB::commit();
            return back()->with('success', 'Pregunta eliminada');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reorderQuestions(Request $request, Survey $survey)
    {
        $request->validate([
            'questions'   => 'required|array',
            'questions.*' => ['integer', new ExistsInSchema('surveys', 'survey_questions', 'id')],
        ]);

        try {
            DB::beginTransaction();
            foreach ($request->questions as $order => $questionId) {
                SurveyQuestion::where('id', $questionId)
                    ->where('survey_id', $survey->id)
                    ->update(['order' => $order]);
            }
            DB::commit();
            return back()->with('success', 'Orden actualizado');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    // ─────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────

    private function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(12);
        } while (Survey::where('slug', $slug)->exists());

        return $slug;
    }
}
