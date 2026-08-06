<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmenityResource as AmeResource;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\SystemVariable;
use App\Models\Administrator\Club;
use App\Services\AmenityAvailabilityService;
use App\Support\SpanishDate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AmenityController extends Controller
{
    private const TIMEZONE = 'America/Mexico_City';

    private const RESERVATION_RULE_KEYS = [
        'dias_para_crear_reserva',
        'dias_para_cancelar_reserva',
        'horas_suspension_reserva',
    ];

    public function __construct(
        private AmenityAvailabilityService $amenityAvailabilityService,
    ) {}

    public function amenitiesByClub(Club $club)
    {
        try {
            $amenities = $club->amenities()->with('resources')->where('is_active', true)->get();

            return $this->ok([
                'amenities' => AmeResource::collection($amenities),
                'rules'     => $this->buildReservationRules($club),
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al obtener las amenidades.');
        }
    }

    public function availableSlots(Request $request, AmenityResource $amenityResource)
    {
        try {
            $validated = $request->validate([
                'date' => ['required', 'date_format:Y-m-d'],
            ]);

            $availableSlots = $this->amenityAvailabilityService->getSlots($amenityResource, $validated['date']);

            return $this->ok($availableSlots);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al obtener los horarios.');
        }
    }

    private function buildReservationRules(Club $club): array
    {
        $variables = SystemVariable::where('club_id', $club->id)
            ->whereIn('name', self::RESERVATION_RULE_KEYS)
            ->pluck('value', 'name');

        $rules = [];
        foreach (self::RESERVATION_RULE_KEYS as $key) {
            $value = $variables->get($key);
            $rules[$key] = is_numeric($value) ? (int) $value : $value;
        }

        $diasParaCrearReserva = $rules['dias_para_crear_reserva'] ?? 1;
        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        $availableDates = collect(range(0, max($diasParaCrearReserva - 1, 0)))
            ->map(function (int $offset) use ($today) {
                $date = $today->copy()->addDays($offset);

                return [
                    'date'  => $date->format('Y-m-d'),
                    'label' => SpanishDate::relativeLabel($date, $today),
                    'day'   => $date->format('d'),
                    'month' => SpanishDate::month($date->month),
                ];
            })
            ->values();

        $rules['available_dates'] = $availableDates;

        return $rules;
    }
}
