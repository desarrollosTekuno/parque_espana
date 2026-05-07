<?php

namespace App\Http\Controllers\Web\AdminClub;

use Inertia\Inertia;
use Illuminate\Routing\Controller;
use App\Models\AdminClub\Survey;
use App\Models\AdminClub\SurveyAnswer;

class SurveyResultController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:surveys.results')->only('index');
    }
    public function index(Survey $survey)
    {
        try {
            $survey->load(['questions.options']);

            $totalResponses = $survey->responses()->whereNotNull('submitted_at')->count();

            $questionsData = $survey->questions->map(function ($question) use ($totalResponses) {
                $answers = SurveyAnswer::where('question_id', $question->id)
                    ->whereHas('response', fn($q) => $q->whereNotNull('submitted_at'))
                    ->get();

                $chartData = null;

                if (in_array($question->type, ['single_choice', 'multiple_choice'])) {
                    // Contar cuántas veces se seleccionó cada opción
                    $counts = [];
                    foreach ($question->options as $opt) {
                        $counts[$opt->id] = ['label' => $opt->option_text, 'count' => 0];
                    }

                    foreach ($answers as $answer) {
                        $selected = $answer->answer_options ?? [];
                        foreach ($selected as $optId) {
                            if (isset($counts[$optId])) {
                                $counts[$optId]['count']++;
                            }
                        }
                    }

                    $chartData = array_values($counts);
                } elseif ($question->type === 'rating') {
                    // Distribución de calificaciones
                    $config = $question->config ?? [];
                    $min = $config['min'] ?? 1;
                    $max = $config['max'] ?? 5;
                    $dist = [];
                    for ($i = $min; $i <= $max; $i++) {
                        $dist[$i] = ['label' => (string) $i, 'count' => 0];
                    }
                    foreach ($answers as $answer) {
                        $val = (int) $answer->answer_text;
                        if (isset($dist[$val])) {
                            $dist[$val]['count']++;
                        }
                    }
                    $avg = $answers->isNotEmpty()
                        ? round($answers->avg(fn($a) => (float) $a->answer_text), 2)
                        : null;
                    $chartData = ['distribution' => array_values($dist), 'average' => $avg];
                } elseif ($question->type === 'open_text') {
                    // Últimas 50 respuestas abiertas
                    $chartData = $answers->take(50)->map(fn($a) => $a->answer_text)->values();
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

            return Inertia::render('AdminClubs/Surveys/Results', [
                'survey' => $survey,
                'totalResponses' => $totalResponses,
                'questions' => $questionsData,
            ]);
        } catch (\Exception $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }
}
