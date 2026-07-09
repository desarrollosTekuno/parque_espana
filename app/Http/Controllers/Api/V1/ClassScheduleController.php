<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Classes\ClassSchedule;
use App\Models\Members\Member;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassScheduleController extends Controller {

    public function list(Request $request, Club $club): JsonResponse {
        try {
            $request->validate([
                'date' => ['nullable', 'date_format:Y-m-d'],
                'type' => ['nullable', 'in:adults,kids'],
                'sport' => ['nullable', 'in:tennis,padel'],
                'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            ]);

            $member = Member::where('user_id', $request->user()->id)->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Socio no encontrado.',
                ], 404);
            }

            $hasMembership = $member->accountMemberships()
                ->whereHas('membershipAccount.memberships', function ($query) use ($club) {
                    $query->where('club_id', $club->id)
                        ->whereIn('status', ['active', 'suspended']);
                })
                ->exists();

            if (!$hasMembership) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este club.',
                ], 403);
            }

            $dayOfWeek = null;

            if ($request->filled('date')) {
                $dayOfWeek = Carbon::parse($request->date)->dayOfWeek;
            } elseif ($request->filled('day_of_week')) {
                $dayOfWeek = $request->day_of_week;
            }

            $classSchedules = ClassSchedule::with(['coach.specialties', 'amenityResource.amenity'])
                ->withCount([
                    'activeEnrollments as active_enrollments_count' => function ($query) use ($request) {
                        if ($request->filled('date')) {
                            $query->whereDate('reservation_date', $request->date);
                        }
                    },
                ])
                ->where('club_id', $club->id)
                ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
                ->when($request->filled('sport'), function ($query) use ($request) {
                    $query->whereHas('coach.specialties', fn ($specialties) =>
                        $specialties->where('code', $request->sport)
                    );
                })
                ->when($dayOfWeek !== null, fn ($query) => $query->where('day_of_week', $dayOfWeek))
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->map(fn ($classSchedule) => [
                    'id' => $classSchedule->id,
                    'name' => $classSchedule->name,
                    'type' => $classSchedule->type,
                    'sports' => $classSchedule->coach?->specialties?->pluck('code')?->values() ?? [],
                    'reservation_date' => $request->date,
                    'day_of_week' => $classSchedule->day_of_week,
                    'start_time' => substr((string) $classSchedule->start_time, 0, 5),
                    'end_time' => substr((string) $classSchedule->end_time, 0, 5),
                    'capacity' => $classSchedule->capacity,
                    'enrolled_count' => $classSchedule->active_enrollments_count,
                    'available_spots' => max($classSchedule->capacity - $classSchedule->active_enrollments_count, 0),
                    'coach' => [
                        'id' => $classSchedule->coach?->id,
                        'full_name' => $classSchedule->coach?->full_name,
                    ],
                    'amenity_resource' => [
                        'id' => $classSchedule->amenityResource?->id,
                        'name' => $classSchedule->amenityResource?->name,
                        'amenity' => [
                            'id' => $classSchedule->amenityResource?->amenity?->id,
                            'name' => $classSchedule->amenityResource?->amenity?->name,
                        ],
                    ],
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Clases obtenidas correctamente.',
                'data' => $classSchedules,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validacion.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clases.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
