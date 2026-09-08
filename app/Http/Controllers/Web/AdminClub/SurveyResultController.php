<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Survey;
use App\Models\AdminClub\SurveyAnswer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class SurveyResultController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:surveys.results')->only(['index', 'exportPdf']);
    }

    public function index(Survey $survey)
    {
        try {
            return Inertia::render('AdminClubs/Surveys/Results', $this->reportData($survey));
        } catch (\Exception $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }

    public function exportPdf(Survey $survey)
    {
        try {
            return Pdf::loadView('exports.survey-results', $this->reportData($survey))
                ->setPaper('a4', 'portrait')
                ->download('resultados-encuesta-' . $survey->id . '.pdf');
        } catch (\Exception $e) {
            report($e);
            return back()->with('error', 'No fue posible generar el PDF.');
        }
    }

    private function reportData(Survey $survey): array
    {
        $survey->load(['questions.options']);
        $totalResponses = $survey->responses()->whereNotNull('submitted_at')->count();

        $questionsData = $survey->questions->map(function ($question) use ($totalResponses) {
            $answers = SurveyAnswer::where('question_id', $question->id)
                ->whereHas('response', fn($query) => $query->whereNotNull('submitted_at'))
                ->get();

            $chartData = null;

            if (in_array($question->type, ['single_choice', 'multiple_choice'])) {
                $counts = [];
                foreach ($question->options as $option) {
                    $counts[$option->id] = ['label' => $option->option_text, 'count' => 0];
                }

                foreach ($answers as $answer) {
                    foreach ($answer->answer_options ?? [] as $optionId) {
                        if (isset($counts[$optionId])) {
                            $counts[$optionId]['count']++;
                        }
                    }
                }

                $chartData = array_values($counts);
            } elseif ($question->type === 'rating') {
                $config = $question->config ?? [];
                $min = $config['min'] ?? 1;
                $max = $config['max'] ?? 5;
                $distribution = [];

                for ($value = $min; $value <= $max; $value++) {
                    $distribution[$value] = ['label' => (string) $value, 'count' => 0];
                }

                foreach ($answers as $answer) {
                    $value = (int) $answer->answer_text;
                    if (isset($distribution[$value])) {
                        $distribution[$value]['count']++;
                    }
                }

                $chartData = [
                    'distribution' => array_values($distribution),
                    'average' => $answers->isNotEmpty()
                        ? round($answers->avg(fn($answer) => (float) $answer->answer_text), 2)
                        : null,
                ];
            } elseif ($question->type === 'open_text') {
                $chartData = $answers->take(50)->map(fn($answer) => $answer->answer_text)->values();
            }

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'config' => $question->config,
                'options' => $question->options,
                'answers_count' => $answers->count(),
                'total_responses' => $totalResponses,
                'chart_data' => $chartData,
            ];
        });

        return [
            'survey' => $survey,
            'totalResponses' => $totalResponses,
            'questions' => $questionsData,
        ];
    }
}
