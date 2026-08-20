<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmenityResource as AmeResource;
use App\Http\Resources\ClassScheduleResource;
use App\Http\Resources\CoachResource;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\SystemVariable;
use App\Models\Administrator\Club;
use App\Models\Classes\ClassSchedule;
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

    public function teachers(AmenityResource $amenityResource)
    {
        try {
            $coaches = $amenityResource->coaches()
                ->with('specialties')
                ->distinct()
                ->get();

            return $this->ok(CoachResource::collection($coaches));
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al obtener los profesores.');
        }
    }

    public function classes(AmenityResource $amenityResource)
    {
        try {
            $now = Carbon::now(self::TIMEZONE);
            $today = Carbon::now(self::TIMEZONE)->dayOfWeek;
            $tomorrow = Carbon::now(self::TIMEZONE)->addDay()->dayOfWeek;
            $currentTime = $now->format('H:i:s');

            $classes = ClassSchedule::with('coach.specialties')
                ->withCount('activeEnrollments')
                ->where('amenity_resource_id', $amenityResource->id)
                ->whereIn('day_of_week', array_unique([$today, $tomorrow]))
                ->where(function ($query) use ($today, $currentTime) {
                    $query->where('day_of_week', '!=', $today)
                          ->orWhere(function ($query) use ($today, $currentTime) {
                                $query->where('day_of_week', $today)
                                ->where('start_time', '>', $currentTime);
                        });
                })
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            return $this->ok(ClassScheduleResource::collection($classes));
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al obtener las clases.');
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
