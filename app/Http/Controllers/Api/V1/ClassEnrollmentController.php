<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Classes\ClassEnrollment;
use App\Models\Classes\ClassSchedule;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccountMember;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassEnrollmentController extends Controller {

    public function reservationMembers(Request $request, Club $club): JsonResponse {
        try {
            $member = $this->getAuthenticatedMember($request);
            $accountMember = $this->getClubAccountMember($member, $club);

            if (!$accountMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este club.',
                ], 403);
            }

            $reservationMembers = $this->getReservationMembers($accountMember);
            $membership = $accountMember->membershipAccount->memberships->first();
            $membershipType = $membership?->membershipType;

            return response()->json([
                'success' => true,
                'message' => 'Miembros para reservar obtenidos correctamente.',
                'data' => [
                    'membership_account' => [
                        'id' => $accountMember->membershipAccount->id,
                        'membership_number' => $accountMember->membershipAccount->membership_number,
                        'account_type' => $accountMember->membershipAccount->account_type,
                        'status' => $accountMember->membershipAccount->status,
                    ],
                    'membership' => [
                        'id' => $membership?->id,
                        'membership_type_id' => $membershipType?->id,
                        'membership_type' => $membershipType?->name,
                        'status' => $membership?->status,
                    ],
                    'logged_member' => $this->formatBookableMember($accountMember),
                    'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                    'members' => $reservationMembers->values(),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener miembros para reservar.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request, Club $club): JsonResponse {
        try {
            $request->validate([
                'member_id' => ['nullable', 'integer', 'min:1'],
                'reservation_date' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $member = $this->getAuthenticatedMember($request);
            $accountMember = $this->getClubAccountMember($member, $club);

            if (!$accountMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este club.',
                ], 403);
            }

            $reservationMembers = $this->getReservationMembers($accountMember);
            $allowedMemberIds = $reservationMembers->pluck('id')->all();

            if ($request->filled('member_id') && !in_array((int) $request->member_id, $allowedMemberIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes consultar inscripciones de este socio.',
                ], 403);
            }

            $enrollments = ClassEnrollment::with([
                'member',
                'reservedByMember',
                'classSchedule.coach.specialties',
                'classSchedule.amenityResource.amenity',
            ])
                ->whereHas('classSchedule', fn ($query) => $query->where('club_id', $club->id))
                ->whereIn('member_id', $allowedMemberIds)
                ->when($request->filled('member_id'), fn ($query) => $query->where('member_id', $request->member_id))
                ->when($request->filled('reservation_date'), fn ($query) => $query->whereDate('reservation_date', $request->reservation_date))
                ->whereNull('cancelled_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($enrollment) => $this->formatEnrollment($enrollment));

            return response()->json([
                'success' => true,
                'message' => 'Inscripciones obtenidas correctamente.',
                'data' => $enrollments,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validacion.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripciones.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request, Club $club): JsonResponse {
        try {
            $validated = $request->validate([
                'class_schedule_id' => ['required', 'integer', 'min:1'],
                'member_id' => ['required', 'integer', 'min:1'],
                'reservation_date' => ['required', 'date_format:Y-m-d'],
            ]);

            $member = $this->getAuthenticatedMember($request);
            $accountMember = $this->getClubAccountMember($member, $club);

            if (!$accountMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este club.',
                ], 403);
            }

            $reservationMembers = $this->getReservationMembers($accountMember);
            $allowedMemberIds = $reservationMembers->pluck('id')->all();

            if (!in_array((int) $validated['member_id'], $allowedMemberIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes reservar esta clase para este socio.',
                ], 403);
            }

            $classSchedule = ClassSchedule::with(['coach.specialties', 'amenityResource.amenity'])
                ->where('club_id', $club->id)
                ->find($validated['class_schedule_id']);

            if (!$classSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clase no encontrada.',
                ], 404);
            }

            if (Carbon::parse($validated['reservation_date'])->dayOfWeek !== (int) $classSchedule->day_of_week) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha no corresponde al dia de la clase.',
                ], 422);
            }

            $existingEnrollment = ClassEnrollment::where('class_schedule_id', $classSchedule->id)
                ->where('member_id', $validated['member_id'])
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->whereNull('cancelled_at')
                ->first();

            if ($existingEnrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio ya está inscrito en esta clase.',
                ], 409);
            }

            $activeEnrollmentCount = ClassEnrollment::where('class_schedule_id', $classSchedule->id)
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->whereNull('cancelled_at')
                ->count();

            if ($activeEnrollmentCount >= $classSchedule->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'La clase ya no tiene cupo disponible.',
                ], 409);
            }

            $hasOverlap = ClassEnrollment::where('member_id', $validated['member_id'])
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->whereNull('cancelled_at')
                ->whereHas('classSchedule', function ($query) use ($classSchedule) {
                    $query->where('day_of_week', $classSchedule->day_of_week)
                        ->where('start_time', '<', $classSchedule->end_time)
                        ->where('end_time', '>', $classSchedule->start_time);
                })
                ->exists();

            if ($hasOverlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio ya tiene otra clase en ese horario.',
                ], 409);
            }

            $enrollment = ClassEnrollment::create([
                'class_schedule_id' => $classSchedule->id,
                'member_id' => $validated['member_id'],
                'reservation_date' => $validated['reservation_date'],
                'reserved_by_member_id' => $member->id,
            ]);

            $enrollment->load([
                'member',
                'reservedByMember',
                'classSchedule.coach.specialties',
                'classSchedule.amenityResource.amenity',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Clase reservada correctamente.',
                'data' => $this->formatEnrollment($enrollment),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validacion.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al reservar clase.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, Club $club, ClassEnrollment $classEnrollment): JsonResponse {
        try {
            $member = $this->getAuthenticatedMember($request);
            $accountMember = $this->getClubAccountMember($member, $club);

            if (!$accountMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a este club.',
                ], 403);
            }

            $reservationMembers = $this->getReservationMembers($accountMember);
            $allowedMemberIds = $reservationMembers->pluck('id')->all();

            $classEnrollment->load('classSchedule');

            if ((int) $classEnrollment->classSchedule?->club_id !== (int) $club->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripcion no encontrada.',
                ], 404);
            }

            if (!in_array((int) $classEnrollment->member_id, $allowedMemberIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes cancelar esta inscripcion.',
                ], 403);
            }

            if ($classEnrollment->cancelled_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'La inscripcion ya fue cancelada.',
                ], 409);
            }

            $classEnrollment->update([
                'cancelled_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inscripcion cancelada correctamente.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar inscripcion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getAuthenticatedMember(Request $request): Member {
        $member = Member::where('user_id', $request->user()->id)->first();

        if (!$member) {
            throw new \RuntimeException('Socio no encontrado.');
        }

        return $member;
    }

    private function getClubAccountMember(Member $member, Club $club): ?MembershipAccountMember {
        return $member->accountMemberships()
            ->with([
                'member',
                'relationship',
                'membershipAccount.memberships' => fn ($query) => $query
                    ->where('club_id', $club->id)
                    ->where('is_primary', true)
                    ->whereIn('status', ['active', 'suspended'])
                    ->with('membershipType'),
                'membershipAccount.accountMembers.member',
                'membershipAccount.accountMembers.relationship',
            ])
            ->whereHas('membershipAccount.memberships', fn ($query) => $query
                ->where('club_id', $club->id)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();
    }

    private function getReservationMembers(MembershipAccountMember $accountMember) {
        if ($accountMember->is_primary_holder) {
            return $accountMember->membershipAccount->accountMembers
                ->map(fn ($item) => $this->formatBookableMember($item));
        }

        return collect([$this->formatBookableMember($accountMember)]);
    }

    private function formatBookableMember(MembershipAccountMember $accountMember): array {
        return [
            'id' => $accountMember->member->id,
            'full_name' => trim(
                "{$accountMember->member->first_name} {$accountMember->member->last_name} {$accountMember->member->second_last_name}"
            ),
            'relationship' => $accountMember->relationship?->name ?? 'Titular',
            'is_primary_holder' => (bool) $accountMember->is_primary_holder,
        ];
    }

    private function formatEnrollment(ClassEnrollment $enrollment): array
    {
        $schedule = $enrollment->classSchedule;

        return [
            'id' => $enrollment->id,
            'member' => [
                'id' => $enrollment->member?->id,
                'full_name' => $enrollment->member?->full_name,
            ],
            'reserved_by_member' => [
                'id' => $enrollment->reservedByMember?->id,
                'full_name' => $enrollment->reservedByMember?->full_name,
            ],
            'reservation_date' => optional($enrollment->reservation_date)?->format('Y-m-d'),
            'class_schedule' => [
                'id' => $schedule?->id,
                'name' => $schedule?->name,
                'type' => $schedule?->type,
                'sports' => $schedule?->coach?->specialties?->pluck('code')?->values() ?? [],
                'day_of_week' => $schedule?->day_of_week,
                'start_time' => $schedule ? substr((string) $schedule->start_time, 0, 5) : null,
                'end_time' => $schedule ? substr((string) $schedule->end_time, 0, 5) : null,
                'capacity' => $schedule?->capacity,
                'coach' => [
                    'id' => $schedule?->coach?->id,
                    'full_name' => $schedule?->coach?->full_name,
                ],
                'amenity_resource' => [
                    'id' => $schedule?->amenityResource?->id,
                    'name' => $schedule?->amenityResource?->name,
                    'amenity' => [
                        'id' => $schedule?->amenityResource?->amenity?->id,
                        'name' => $schedule?->amenityResource?->amenity?->name,
                    ],
                ],
            ],
            'created_at' => optional($enrollment->created_at)?->format('Y-m-d H:i:s'),
            'cancelled_at' => optional($enrollment->cancelled_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
