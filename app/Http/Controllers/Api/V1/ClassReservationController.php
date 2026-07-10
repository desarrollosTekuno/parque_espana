<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classes\ClassEnrollment;
use App\Models\Classes\ClassSchedule;
use App\Models\Classes\ClassSession;
use App\Models\Members\Member;
use App\Models\Memberships\Membership;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassReservationController extends Controller {

    public function createReservation(Request $request, int $id): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                'member_id'             => ['required', 'integer'],
                'reserved_by_member_id' => ['required', 'integer'],
                'reservation_date'      => ['required', 'date_format:Y-m-d'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 500);
            }

            $class = ClassSchedule::find($id);

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clase no encontrada.',
                ], 500);
            }

            $reservationDate = $request->reservation_date;
            $dayOfWeek = Carbon::parse($reservationDate)->dayOfWeek;

            if ($dayOfWeek !== $class->day_of_week) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha no corresponde al dia de la clase.',
                ], 500);
            }

            $session = ClassSession::firstOrCreate(
                ['class_schedule_id' => $class->id, 'date' => $reservationDate],
                [
                    'start_time' => $class->start_time,
                    'end_time'   => $class->end_time,
                    'capacity'   => $class->capacity,
                    'coach_id'   => $class->coach_id,
                    'status'     => 'scheduled',
                ]
            );

            if ($session->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta sesion fue cancelada.',
                ], 500);
            }

            $member = Member::find($request->member_id);

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Socio no encontrado.',
                ], 500);
            }

            $accountIds = $member->accountMemberships()->pluck('membership_account_id');

            $hasActiveMembership = Membership::whereIn('membership_account_id', $accountIds)
                ->where('club_id', $class->club_id)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->exists();

            if (!$hasActiveMembership) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio no tiene membresia activa en este club.',
                ], 500);
            }

            $duplicate = ClassEnrollment::where('class_session_id', $session->id)
                ->where('member_id', $request->member_id)
                ->whereNull('cancelled_at')
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio ya esta inscrito en esta clase.',
                ], 500);
            }

            $enrolledCount = ClassEnrollment::where('class_session_id', $session->id)
                ->whereNull('cancelled_at')
                ->count();

            if ($enrolledCount >= $session->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'La clase ya no tiene cupo disponible.',
                ], 500);
            }

            $overlap = ClassEnrollment::where('member_id', $request->member_id)
                ->whereNull('cancelled_at')
                ->whereHas('classSession', function ($query) use ($session) {
                    $query->where('date', $session->date)
                        ->where('start_time', '<', $session->end_time)
                        ->where('end_time', '>', $session->start_time);
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio ya tiene otra clase en ese horario.',
                ], 500);
            }

            $enrollment = ClassEnrollment::create([
                'class_session_id'      => $session->id,
                'member_id'             => $request->member_id,
                'reserved_by_member_id' => $request->reserved_by_member_id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $enrollment,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al reservar la clase.',
            ], 500);
        }
    }
}
