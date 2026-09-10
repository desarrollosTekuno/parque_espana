<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #263238; font-size: 10px; }
        .header { background: #1f4e78; color: #fff; padding: 17px 20px; margin-bottom: 18px; }
        .header h1 { font-size: 19px; margin: 0 0 5px; }
        .header p { margin: 0; font-size: 10px; color: #d9eaf7; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 18px; }
        .summary td { width: 33.33%; text-align: center; padding: 12px 8px; background: #f5f8fb; border: 1px solid #d9e2f3; }
        .metric { color: #1f4e78; font-size: 22px; font-weight: bold; }
        .label { color: #607d8b; font-size: 9px; margin-top: 4px; }
        .status-active { color: #2e7d32; font-size: 16px; font-weight: bold; }
        .status-draft { color: #607d8b; font-size: 16px; font-weight: bold; }
        .question { border: 1px solid #d9e2f3; margin-bottom: 14px; page-break-inside: avoid; }
        .question-title { background: #f5f8fb; padding: 10px 12px; border-bottom: 1px solid #d9e2f3; }
        .question-number { color: #607d8b; font-size: 9px; margin-bottom: 4px; }
        .question-text { font-size: 11px; font-weight: bold; }
        .chip { float: right; background: #d9eaf7; color: #1f4e78; padding: 3px 7px; font-size: 8px; border-radius: 8px; }
        .answer-count { float: right; color: #6a1b9a; margin-top: 5px; font-size: 9px; }
        .content { padding: 10px 12px; }
        .option { margin: 0 0 8px; }
        .option-label { width: 75%; display: inline-block; }
        .option-value { width: 22%; display: inline-block; text-align: right; font-weight: bold; }
        .bar { height: 8px; background: #e8edf2; margin-top: 3px; }
        .bar-fill { height: 8px; background: #2f75b5; }
        .rating-average { font-size: 20px; color: #b26a00; font-weight: bold; margin-bottom: 10px; }
        .comment { background: #f5f5f5; border-left: 3px solid #009688; padding: 8px; margin-bottom: 6px; }
        .empty { color: #607d8b; }
        .clear { clear: both; }
        .footer { position: fixed; bottom: -14px; left: 0; right: 0; color: #78909c; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    @php
        $typeLabel = ['single_choice' => 'Opción múltiple', 'multiple_choice' => 'Casillas', 'open_text' => 'Texto abierto', 'rating' => 'Valoración'];
        $percentage = fn ($count, $total) => $total ? round(($count / $total) * 100) : 0;
    @endphp

    <div class="header">
        <h1>Resultados de encuesta</h1>
        <p>{{ $survey->title }}@if($survey->description) - {{ $survey->description }}@endif</p>
    </div>

    <table class="summary">
        <tr>
            <td><div class="metric">{{ $totalResponses }}</div><div class="label">RESPUESTAS RECIBIDAS</div></td>
            <td><div class="metric">{{ $questions->count() }}</div><div class="label">PREGUNTAS</div></td>
            <td><div class="{{ $survey->status === 'active' ? 'status-active' : 'status-draft' }}">{{ $survey->status === 'active' ? 'Activa' : 'Borrador' }}</div><div class="label">ESTADO</div></td>
        </tr>
    </table>

    @if($totalResponses === 0)
        <p class="empty">Aún no hay respuestas para esta encuesta.</p>
    @endif

    @foreach($questions as $index => $question)
        <div class="question">
            <div class="question-title">
                <span class="chip">{{ $typeLabel[$question['type']] }}</span>
                <div class="question-number">PREGUNTA {{ $index + 1 }}</div>
                <div class="question-text">{{ $question['question_text'] }}</div>
                <span class="answer-count">{{ $question['answers_count'] }} respuesta(s)</span>
                <div class="clear"></div>
            </div>
            <div class="content">
                @if(in_array($question['type'], ['single_choice', 'multiple_choice']))
                    @foreach($question['chart_data'] as $item)
                        <div class="option">
                            <span class="option-label">{{ $item['label'] }}</span>
                            <span class="option-value">{{ $item['count'] }} ({{ $percentage($item['count'], $question['answers_count']) }}%)</span>
                            <div class="bar"><div class="bar-fill" style="width: {{ $percentage($item['count'], $question['answers_count']) }}%"></div></div>
                        </div>
                    @endforeach
                @elseif($question['type'] === 'rating')
                    <div class="rating-average">{{ $question['chart_data']['average'] ?? '—' }} <span style="font-size: 10px; color: #607d8b; font-weight: normal;">/ {{ $question['config']['max'] ?? 5 }}</span></div>
                    @foreach($question['chart_data']['distribution'] as $item)
                        <div class="option">
                            <span class="option-label">Calificación {{ $item['label'] }}</span>
                            <span class="option-value">{{ $item['count'] }} ({{ $percentage($item['count'], $question['answers_count']) }}%)</span>
                            <div class="bar"><div class="bar-fill" style="width: {{ $percentage($item['count'], $question['answers_count']) }}%; background: #f0ad00;"></div></div>
                        </div>
                    @endforeach
                @else
                    @forelse($question['chart_data'] as $text)
                        <div class="comment">{{ $text }}</div>
                    @empty
                        <div class="empty">Sin respuestas aún.</div>
                    @endforelse
                @endif
            </div>
        </div>
    @endforeach

    <div class="footer">Reporte generado el {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
