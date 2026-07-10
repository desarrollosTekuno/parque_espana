<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classes\ClassEnrollment;
use App\Models\Classes\ClassSchedule;
use App\Models\Classes\ClassSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller {

    public function getClasses(Request $request): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                'club_id'      => ['required', 'integer'],
                'specialty_id' => ['nullable', 'integer'],
                'coach_id'     => ['nullable', 'integer'],
                'type'         => ['nullable', 'in:adults,kids'],
                'day_of_week'  => ['nullable', 'integer', 'between:0,6'],
                'name'         => ['nullable', 'string', 'max:255'],
                'date'         => ['nullable', 'date_format:Y-m-d'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 500);
            }

            $query = ClassSchedule::with(['coach', 'specialty', 'amenityResource.amenity'])
                ->where('club_id', $request->club_id);

            if ($request->filled('specialty_id')) {
                $query->where('specialty_id', $request->specialty_id);
            }

            if ($request->filled('coach_id')) {
                $query->where('coach_id', $request->coach_id);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('day_of_week')) {
                $query->where('day_of_week', $request->day_of_week);
            }

            if ($request->filled('name')) {
                $query->where('name', 'ILIKE', '%' . $request->name . '%');
            }

            if ($request->filled('date')) {
                $dayOfWeek = Carbon::parse($request->date)->dayOfWeek;
                $query->where('day_of_week', $dayOfWeek);
            }

            $classes = $query->orderBy('day_of_week')->orderBy('start_time')->get();

            if ($request->filled('date')) {
                foreach ($classes as $class) {
                    $session = ClassSession::firstOrCreate(
                        ['class_schedule_id' => $class->id, 'date' => $request->date],
                        [
                            'start_time' => $class->start_time,
                            'end_time'   => $class->end_time,
                            'capacity'   => $class->capacity,
                            'coach_id'   => $class->coach_id,
                            'status'     => 'scheduled',
                        ]
                    );

                    $enrolledCount = ClassEnrollment::where('class_session_id', $session->id)
                        ->whereNull('cancelled_at')
                        ->count();

                    $class->available_spots = $session->capacity - $enrolledCount;
                    $class->session_status = $session->status;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $classes,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clases.',
            ], 500);
        }
    }

    public function getClass(int $id): JsonResponse {
        try {
            $class = ClassSchedule::with(['coach', 'specialty', 'amenityResource.amenity'])->find($id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clase no encontrada.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $class,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la clase.',
            ], 500);
        }
    }
}
