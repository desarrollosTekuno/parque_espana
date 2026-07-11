<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Classes\ClassSchedule;
use App\Models\Classes\ClassSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    public function getClasses(Request $request): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                'club_id' => ['required', 'integer'],
                'specialty_id' => ['required', 'integer'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 500);
            }

            $scheduleIds = ClassSchedule::where('club_id', $request->club_id)
                ->where('specialty_id', $request->specialty_id)
                ->where('is_active', true)
                ->pluck('id');

            $sessions = ClassSession::whereIn('class_schedule_id', $scheduleIds)
                ->where('status', 'scheduled')
                ->where('date', '>=', now()->toDateString())
                ->with([
                    'classSchedule:id,name,type',
                    'coach:id,first_name,last_name,second_last_name',
                    'amenityResource:id,name',
                ])
                ->orderBy('date')
                ->orderBy('start_time')
                ->get([
                    'id', 'date', 'start_time', 'end_time', 'capacity', 'current_capacity',
                    'class_schedule_id', 'coach_id', 'amenity_resource_id',
                ]);

            $sessions = $sessions->map(function ($session) {
                $data = $session->toArray();
                $data['date'] = $session->date->format('Y-m-d');

                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => $sessions,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las clases.',
            ], 500);
        }
    }
}
