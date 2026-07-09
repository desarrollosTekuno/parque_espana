<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Classes\ClassEnrollment;
use App\Models\Classes\ClassSchedule;
use App\Models\Classes\Specialty;
use App\Models\Members\Member;
use App\Models\Memberships\MembershipAccountMember;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClassReservationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:classReservations.index')->only(['index', 'account', 'schedules']);
        $this->middleware('permission:classReservations.store')->only('store');
        $this->middleware('permission:classReservations.cancel')->only('cancel');
    }

    public function index(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');

        $members = Member::byClub($clubId)
            ->select('id', 'first_name', 'last_name', 'second_last_name', 'email')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($member) => $this->formatMember($member));

        $specialties = Specialty::where('club_id', $clubId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $enrollments = ClassEnrollment::with([
            'member',
            'reservedByMember',
            'classSchedule.coach',
            'classSchedule.amenityResource.amenity',
        ])
            ->whereHas('classSchedule', fn ($query) => $query->where('club_id', $clubId))
            ->whereNull('cancelled_at')
            ->orderByDesc('reservation_date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($enrollment) => $this->formatEnrollment($enrollment));

        return Inertia::render('AdminClubs/ClassReservations/Index', [
            'members' => $members,
            'specialties' => $specialties,
            'enrollments' => $enrollments,
        ]);
    }

    public function account(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'member_id' => ['required', 'integer'],
            ]);

            $clubId = session('club_id');
            $member = Member::find($validated['member_id']);
            $accountMember = $this->getClubAccountMember($member, $clubId);

            if (!$accountMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'El socio no tiene membresia activa en este club.',
                ], 404);
            }

            $membership = $accountMember->membershipAccount->memberships->first();
            $membershipType = $membership?->membershipType;
            $reservationMembers = $this->getReservationMembers($accountMember);

            return response()->json([
                'success' => true,
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
                    'logged_member' => $this->formatAccountMember($accountMember),
                    'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                    'members' => $reservationMembers->values(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos invalidos.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function schedules(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date' => ['required', 'date_format:Y-m-d'],
                'type' => ['nullable', 'in:adults,kids'],
                'sport' => ['nullable', 'string'],
            ]);

            $clubId = session('club_id');
            $dayOfWeek = Carbon::parse($validated['date'])->dayOfWeek;

            $classSchedules = ClassSchedule::with(['coach.specialties', 'amenityResource.amenity'])
                ->withCount([
                    'activeEnrollments as active_enrollments_count' => fn ($query) =>
                        $query->whereDate('reservation_date', $validated['date']),
                ])
                ->where('club_id', $clubId)
                ->where('day_of_week', $dayOfWeek)
                ->when($request->filled('type'), fn ($query) => $query->where('type', $validated['type']))
                ->when($request->filled('sport'), function ($query) use ($validated) {
                    $query->whereHas('coach.specialties', fn ($specialties) =>
                        $specialties->where('code', $validated['sport'])
                    );
                })
                ->orderBy('start_time')
                ->get()
                ->map(fn ($classSchedule) => $this->formatSchedule($classSchedule, $validated['date']));

            return response()->json([
                'success' => true,
                'data' => $classSchedules,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos invalidos.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'logged_member_id' => ['required', 'integer'],
                'member_id' => ['required', 'integer'],
                'class_schedule_id' => ['required', 'integer'],
                'reservation_date' => ['required', 'date_format:Y-m-d'],
            ]);

            $clubId = session('club_id');
            $loggedMember = Member::find($validated['logged_member_id']);
            $accountMember = $this->getClubAccountMember($loggedMember, $clubId);

            if (!$accountMember) {
                return back()->withErrors(['messageError' => 'El socio no tiene membresia activa en este club.']);
            }

            $allowedMemberIds = $this->getReservationMembers($accountMember)->pluck('id')->all();

            if (!in_array((int) $validated['member_id'], $allowedMemberIds, true)) {
                return back()->withErrors(['messageError' => 'Este socio no puede reservar para el integrante seleccionado.']);
            }

            $classSchedule = ClassSchedule::where('club_id', $clubId)->find($validated['class_schedule_id']);

            if (!$classSchedule) {
                return back()->withErrors(['messageError' => 'Clase no encontrada.']);
            }

            if (Carbon::parse($validated['reservation_date'])->dayOfWeek !== (int) $classSchedule->day_of_week) {
                return back()->withErrors(['messageError' => 'La fecha no corresponde al dia de la clase.']);
            }

            $existingEnrollment = ClassEnrollment::where('class_schedule_id', $classSchedule->id)
                ->where('member_id', $validated['member_id'])
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->whereNull('cancelled_at')
                ->first();

            if ($existingEnrollment) {
                return back()->withErrors(['messageError' => 'El socio ya esta inscrito en esta clase.']);
            }

            $activeEnrollmentCount = ClassEnrollment::where('class_schedule_id', $classSchedule->id)
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->whereNull('cancelled_at')
                ->count();

            if ($activeEnrollmentCount >= $classSchedule->capacity) {
                return back()->withErrors(['messageError' => 'La clase ya no tiene cupo disponible.']);
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
                return back()->withErrors(['messageError' => 'El socio ya tiene otra clase en ese horario.']);
            }

            ClassEnrollment::create([
                'class_schedule_id' => $classSchedule->id,
                'member_id' => $validated['member_id'],
                'reservation_date' => $validated['reservation_date'],
                'reserved_by_member_id' => $loggedMember->id,
            ]);

            return back()->with('success', 'Reservacion creada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors(['messageError' => 'Ocurrio un error al crear la reservacion.']);
        }
    }

    public function cancel(Request $request, ClassEnrollment $classEnrollment)
    {
        try {
            $validated = $request->validate([
                'logged_member_id' => ['required', 'integer'],
            ]);

            $clubId = session('club_id');
            $loggedMember = Member::find($validated['logged_member_id']);
            $accountMember = $this->getClubAccountMember($loggedMember, $clubId);

            if (!$accountMember) {
                return back()->withErrors(['messageError' => 'El socio no tiene membresia activa en este club.']);
            }

            $allowedMemberIds = $this->getReservationMembers($accountMember)->pluck('id')->all();
            $classEnrollment->load('classSchedule');

            if ((int) $classEnrollment->classSchedule?->club_id !== (int) $clubId) {
                return back()->withErrors(['messageError' => 'Reservacion no encontrada.']);
            }

            if (!in_array((int) $classEnrollment->member_id, $allowedMemberIds, true)) {
                return back()->withErrors(['messageError' => 'Este socio no puede cancelar esta reservacion.']);
            }

            $classEnrollment->update([
                'cancelled_at' => now(),
            ]);

            return back()->with('success', 'Reservacion cancelada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors(['messageError' => 'Ocurrio un error al cancelar la reservacion.']);
        }
    }

    private function getClubAccountMember(?Member $member, int $clubId): ?MembershipAccountMember
    {
        if (!$member) {
            return null;
        }

        return $member->accountMemberships()
            ->with([
                'member',
                'relationship',
                'membershipAccount.memberships' => fn ($query) => $query
                    ->where('club_id', $clubId)
                    ->where('is_primary', true)
                    ->whereIn('status', ['active', 'suspended'])
                    ->with('membershipType'),
                'membershipAccount.accountMembers.member',
                'membershipAccount.accountMembers.relationship',
            ])
            ->whereHas('membershipAccount.memberships', fn ($query) => $query
                ->where('club_id', $clubId)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
            )
            ->first();
    }

    private function getReservationMembers(MembershipAccountMember $accountMember)
    {
        if ($accountMember->is_primary_holder) {
            return $accountMember->membershipAccount->accountMembers
                ->map(fn ($item) => $this->formatAccountMember($item));
        }

        return collect([$this->formatAccountMember($accountMember)]);
    }

    private function formatAccountMember(MembershipAccountMember $accountMember): array
    {
        return [
            'id' => $accountMember->member->id,
            'full_name' => $accountMember->member->full_name,
            'relationship' => $accountMember->relationship?->name ?? 'Titular',
            'is_primary_holder' => (bool) $accountMember->is_primary_holder,
        ];
    }

    private function formatMember(Member $member): array
    {
        return [
            'id' => $member->id,
            'full_name' => $member->full_name,
            'email' => $member->email,
        ];
    }

    private function formatSchedule(ClassSchedule $classSchedule, string $date): array
    {
        return [
            'id' => $classSchedule->id,
            'name' => $classSchedule->name,
            'type' => $classSchedule->type,
            'reservation_date' => $date,
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
            'class_name' => $schedule?->name,
            'start_time' => $schedule ? substr((string) $schedule->start_time, 0, 5) : null,
            'end_time' => $schedule ? substr((string) $schedule->end_time, 0, 5) : null,
        ];
    }
}
