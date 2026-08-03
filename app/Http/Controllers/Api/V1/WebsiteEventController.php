<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Website\WebsiteEvent;
use Illuminate\Http\Request;

class WebsiteEventController extends Controller
{
    private const TYPES = [
        'activity' => ['label' => 'Actividad', 'color' => '#0097A7'],
        'celebration' => ['label' => 'Celebración', 'color' => '#EC659C'],
        'holiday' => ['label' => 'Día festivo', 'color' => '#F4B400'],
    ];

    public function index(Request $request, Club $club)
    {
        try {
            $query = WebsiteEvent::where('club_id', $club->id);

            if ($request->filled('year')) {
                $query->whereYear('event_date', (int) $request->year);
            }

            if ($request->filled('month')) {
                $query->whereMonth('event_date', (int) $request->month);
            }

            $events = $query->orderBy('event_date')
                ->orderBy('id')
                ->get()
                ->map(function ($event) {
                    $type = self::TYPES[$event->type];

                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'date' => $event->event_date->format('Y-m-d'),
                        'type' => $event->type,
                        'type_label' => $type['label'],
                        'color' => $type['color'],
                    ];
                });

            return $this->ok($events);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('Error al obtener los eventos.');
        }
    }
}
