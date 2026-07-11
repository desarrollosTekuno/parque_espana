<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Classes\ClassEnrollment;
use App\Models\Classes\ClassSession;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccountMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClassEnrollmentController extends Controller {
    public function store(Request $request): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                'class_session_id' => ['required', 'integer'],
                'member_id' => ['required', 'integer'],
                'enrolled_by_member_id' => ['required', 'integer'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 500);
            }

            $memberId = $request->member_id;
            $enrolledByMemberId = $request->enrolled_by_member_id;

            $member = Member::find($memberId);
            $enrolledByMember = Member::find($enrolledByMemberId);

            if (!$member || !$enrolledByMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Socio no encontrado.',
                ], 500);
            }

            if ($memberId !== $enrolledByMemberId) {
                $titularAccountIds = MembershipAccountMember::where('member_id', $enrolledByMemberId)
                    ->where('is_primary_holder', true)
                    ->pluck('membership_account_id');

                $isAuthorized = MembershipAccountMember::where('member_id', $memberId)
                    ->whereIn('membership_account_id', $titularAccountIds)
                    ->exists();

                if (!$isAuthorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo el titular de la cuenta puede inscribir a otra persona.',
                    ], 500);
                }
            }

            $enrollment = DB::transaction(function () use ($request, $memberId, $enrolledByMemberId) {
                $session = ClassSession::where('id', $request->class_session_id)
                    ->lockForUpdate()
                    ->first();

                if (!$session) {
                    throw new \RuntimeException('Sesión no encontrada.');
                }

                if ($session->status !== 'scheduled') {
                    throw new \RuntimeException('Esta sesión no está disponible para reservar.');
                }

                $yaInscrito = ClassEnrollment::where('class_session_id', $session->id)
                    ->where('member_id', $memberId)
                    ->whereNull('cancelled_at')
                    ->exists();

                if ($yaInscrito) {
                    throw new \RuntimeException('Este socio ya está inscrito en esta sesión.');
                }

                if ($session->current_capacity >= $session->capacity) {
                    throw new \RuntimeException('No hay cupo disponible para esta sesión.');
                }

                $enrollment = ClassEnrollment::create([
                    'class_session_id' => $session->id,
                    'member_id' => $memberId,
                    'enrolled_by_member_id' => $enrolledByMemberId,
                ]);

                $session->increment('current_capacity');

                return $enrollment;
            });

            return response()->json([
                'success' => true,
                'data' => $enrollment,
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la reservación.',
            ], 500);
        }
    }

    public function cancel(Request $request, int $enrollment): JsonResponse {
        try {
            $validator = Validator::make($request->all(), [
                'cancelled_by_member_id' => ['required', 'integer'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 500);
            }

            $cancelledByMemberId = $request->cancelled_by_member_id;

            $enrollmentModel = ClassEnrollment::whereNull('cancelled_at')->find($enrollment);

            if (!$enrollmentModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservación no encontrada o ya cancelada.',
                ], 500);
            }

            if ($cancelledByMemberId !== $enrollmentModel->member_id) {
                $titularAccountIds = MembershipAccountMember::where('member_id', $cancelledByMemberId)
                    ->where('is_primary_holder', true)
                    ->pluck('membership_account_id');

                $isAuthorized = MembershipAccountMember::where('member_id', $enrollmentModel->member_id)
                    ->whereIn('membership_account_id', $titularAccountIds)
                    ->exists();

                if (!$isAuthorized) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo el titular de la cuenta puede cancelar la reservación de otra persona.',
                    ], 500);
                }
            }

            DB::transaction(function () use ($enrollmentModel) {
                $session = ClassSession::where('id', $enrollmentModel->class_session_id)
                    ->lockForUpdate()
                    ->first();

                $enrollmentModel->update(['cancelled_at' => now()]);

                if ($session->current_capacity > 0) {
                    $session->decrement('current_capacity');
                }
            });

            return response()->json([
                'success' => true,
                'data' => $enrollmentModel,
            ], 200);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la reservación.',
            ], 500);
        }
    }
}
