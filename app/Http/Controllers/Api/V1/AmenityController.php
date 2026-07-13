<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmenityResource as AmeResource;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\SystemVariable;
use App\Models\Administrator\Club;
use App\Services\AmenityAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class AmenityController extends Controller {

    private const TIMEZONE = 'America/Mexico_City';

    private const MONTHS_ES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private const WEEKDAYS_ES = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado',
    ];

    // Nombres de reservations.system_variables que se exponen como reglas de reservación en la app.
    // Para agregar una nueva regla basta con sumar su nombre aquí.
    private const RESERVATION_RULE_KEYS = [
        'dias_para_crear_reserva',
        'dias_para_cancelar_reserva',
        'horas_suspension_reserva',
    ];

    protected $amenityAvailabilityService;

    public function __construct(AmenityAvailabilityService $amenityAvailabilityService)
    {
        $this->amenityAvailabilityService = $amenityAvailabilityService;
    }

    public function index()
    {
        try {

            $amenities = Amenity::with('resources')->where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'message' => 'Amenidades obtenidas correctamente',
                'amenities' => AmeResource::collection($amenities)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener las amenidades',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function amenitiesByClub(Club $club)
    {
        try {
            $amenities = $club->amenities()->with('resources')->where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'message' => 'Amenidades obtenidas correctamente',
                'amenities' => AmeResource::collection($amenities),
                'rules' => $this->buildReservationRules($club)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener las amenidades',
                'error_details' => $e->getMessage()
            ], 500);
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
                    'date' => $date->format('Y-m-d'),
                    'label' => match ($offset) {
                        0 => 'Hoy',
                        1 => 'Mañana',
                        default => self::WEEKDAYS_ES[$date->dayOfWeek],
                    },
                    'day' => $date->format('d'),
                    'month' => self::MONTHS_ES[$date->month],
                ];
            })
            ->values();

        $rules['available_dates'] = $availableDates;

        return $rules;
    }

    public function availableSlots(Request $request, AmenityResource $amenityResource)
    {
        try {
            $validated = $request->validate([
                'date' => ['required', 'date_format:Y-m-d'],
            ]);

            $availableSlots = $this->amenityAvailabilityService->getSlots($amenityResource, $validated['date']);

            return response()->json([
                'success' => true,
                'message' => 'Horarios obtenidos correctamente',
                'available_slots' => $availableSlots
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al obtener los horarios',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}
