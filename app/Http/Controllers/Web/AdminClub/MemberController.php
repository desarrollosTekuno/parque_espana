<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\MaritalStatus;
use App\Models\Catalogs\Relationship;
use App\Models\Catalogs\State;
use App\Models\Members\Address;
use App\Models\Members\EmploymentInfo;
use App\Models\Members\Member;
use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Services\Billing\MembershipChargeService;
use App\Rules\ExistsInSchema;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MemberController extends Controller
{
    public function __construct(
        protected MembershipChargeService $membershipChargeService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $prefix = 'members';
            $driver = DB::getDriverName();

            $query = MembershipAccount::query()
                ->with([
                    'primaryHolder.member',
                    'memberships' => fn($membershipQuery) => $membershipQuery
                        ->with(['membershipType', 'club'])
                        ->where('status', 'active')
                        ->where('is_primary', true),
                ])
                ->withCount('accountMembers')
                ->whereHas('memberships', function (Builder $membershipQuery) use ($clubId) {
                    $membershipQuery->where('club_id', $clubId)
                        ->where('status', 'active')
                        ->where('is_primary', true);
                })
                ->whereHas('primaryHolder.member');

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('membership_number', $like, "%{$search}%")
                        ->orWhereHas('primaryHolder.member', function (Builder $memberQuery) use ($search, $like) {
                            $memberQuery->where('first_name', $like, "%{$search}%")
                                ->orWhere('last_name', $like, "%{$search}%")
                                ->orWhere('second_last_name', $like, "%{$search}%")
                                ->orWhere('email', $like, "%{$search}%")
                                ->orWhere('phone', $like, "%{$search}%");
                        })->orWhereHas('memberships.membershipType', function (Builder $membershipTypeQuery) use ($search, $like) {
                            $membershipTypeQuery->where('name', $like, "%{$search}%");
                        });
                });
            }

            $sortMap = [
                'id' => 'id',
                'membership_number' => 'membership_number',
                'created_at' => 'created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'id');
            $order = $request->input("{$prefix}_order", 'desc');
            $sortColumn = $sortMap[$sort] ?? 'id';

            $pendingMembersCount = Membership::query()
                ->where('club_id', $clubId)
                ->where('status', 'pending')
                ->where('is_primary', true)
                ->count();

            $members = $query
                ->orderBy($sortColumn, $order)
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (MembershipAccount $account) use ($clubId) {
                    $holder = $account->primaryHolder?->member;
                    $activeMemberships = $account->memberships
                        ->where('club_id', (int) $clubId)
                        ->values();
                    $billableMembership = $activeMemberships->firstWhere('is_billable', true);
                    $currentMembership = $billableMembership ?? $activeMemberships->first();
                    $currentMembershipCode = (string) ($currentMembership?->membershipType?->code ?? '');
                    $fullName = trim(collect([
                        $holder?->first_name,
                        $holder?->last_name,
                        $holder?->second_last_name,
                    ])->filter()->implode(' '));

                    return [
                        'id' => $account->id,
                        'membership_id' => $currentMembership?->id,
                        'membership_number' => $account->membership_number,
                        'holder_name' => $fullName,
                        'email' => $holder?->email,
                        'phone' => $holder?->phone,
                        'monthly_fee' => (float) ($billableMembership?->monthly_fee ?? 0),
                        'status' => $currentMembership?->status,
                        'can_change_membership' => $currentMembership !== null
                            && Str::contains($currentMembershipCode, '_IND'),
                        'can_change_primary_holder' => (bool) ($currentMembership?->membershipType?->allows_multiple_members)
                            && (int) $account->account_members_count > 1,
                        'can_separate_member' => (bool) ($currentMembership?->membershipType?->allows_multiple_members)
                            && (int) $account->account_members_count > 1,
                        'active_memberships' => $activeMemberships->map(function (Membership $membership) {
                            return [
                                'id' => $membership->id,
                                'membership_type_name' => $membership->membershipType?->name,
                                'membership_type_code' => $membership->membershipType?->code,
                                'club_name' => $membership->club?->name,
                                'club_code' => $membership->club?->code,
                                'monthly_fee' => (float) $membership->monthly_fee,
                                'is_billable' => (bool) $membership->is_billable,
                                'start_date' => $membership->start_date,
                                'end_date' => $membership->end_date,
                                'status' => $membership->status,
                            ];
                        })->values(),
                    ];
                })
                ->appends($request->all());

            return Inertia::render('Members/Index', [
                'members' => $members,
                'pendingMembersCount' => $pendingMembersCount,
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('Members/Index', [
                'members' => [
                    'data' => [],
                    'total' => 0,
                ],
                'pendingMembersCount' => 0,
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function create()
    {
        $clubId = session('club_id');
        $membershipTypes = MembershipType::where('show_in_listing', true)
            ->select('id', 'club_id', 'code', 'name', 'description', 'allows_multiple_members', 'validity_months')
            ->with([
                'documentTypes:id,name,allowed_extensions',
                'documentTypes.relationships:id,name',
            ])
            ->where('club_id', $clubId)
            ->orderBy('created_at', 'desc')
            ->get();
        $originMembershipTypes = MembershipType::select('id', 'club_id', 'code', 'name', 'allows_multiple_members')
            ->orderBy('name')
            ->get();
        $clubs = Club::select('id', 'code', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Members/Create', [
            'membershipTypes' => $membershipTypes,
            'originMembershipTypes' => $originMembershipTypes,
            'clubs' => $clubs,
            ...$this->getCreateFormCatalogs(),
        ]);
    }

    public function locationStates(Request $request)
    {
        $validated = $request->validate([
            'country_id' => ['required', new ExistsInSchema('catalogs', 'countries', 'id')],
        ]);

        return response()->json(
            State::query()
                ->select('id', 'country_id', 'name')
                ->where('country_id', $validated['country_id'])
                ->orderBy('name')
                ->get()
        );
    }

    public function locationCities(Request $request)
    {
        $validated = $request->validate([
            'state_id' => ['required', new ExistsInSchema('catalogs', 'states', 'id')],
        ]);

        return response()->json(
            City::query()
                ->select('id', 'country_id', 'state_id', 'name')
                ->where('state_id', $validated['state_id'])
                ->orderBy('name')
                ->get()
        );
    }

    public function pricingPreview(Request $request)
    {
        try {
            $sessionClubId = session('club_id');

            if (!$sessionClubId) {
                return response()->json([
                    'message' => 'No hay un club seleccionado en la sesion.',
                ], 422);
            }

            $validated = $request->validate([
                'source_membership_id' => ['nullable', new ExistsInSchema('memberships', 'memberships', 'id')],
                'target_club_id' => ['nullable', new ExistsInSchema('clubs', 'clubs', 'id')],
                'membership_type_id' => ['required', new ExistsInSchema('memberships', 'types', 'id')],
                'from_membership_type_id' => ['nullable', new ExistsInSchema('memberships', 'types', 'id')],
                'source_club_id' => ['nullable', new ExistsInSchema('clubs', 'clubs', 'id')],
                'has_multiple_clubs' => ['nullable', 'boolean'],
                'source_membership_is_active' => ['nullable', 'boolean'],
                'years_in_source_club' => ['nullable', 'integer', 'min:0', 'max:99'],
                'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            ]);

            $clubId = $validated['target_club_id'] ?? $sessionClubId;
            $sourceMembership = null;
            $fromMembershipType = null;
            $sourceClub = null;
            $hasMultipleClubs = (bool) ($validated['has_multiple_clubs'] ?? false);
            $sourceMembershipIsActive = (bool) ($validated['source_membership_is_active'] ?? false);
            $yearsInSourceClub = array_key_exists('years_in_source_club', $validated)
                && $validated['years_in_source_club'] !== null
                ? (int) $validated['years_in_source_club']
                : null;
            $age = array_key_exists('age', $validated) && $validated['age'] !== null
                ? (int) $validated['age']
                : null;
            $sameClubTransition = false;
            $currentMonthlyFee = null;

            if (!empty($validated['source_membership_id'])) {
                $sourceMembership = Membership::query()
                    ->with([
                        'membershipType',
                        'club',
                        'account.primaryHolder.member',
                        'account.accountMembers',
                    ])
                    ->findOrFail($validated['source_membership_id']);

                $fromMembershipType = $sourceMembership->membershipType;
                $sourceClub = $sourceMembership->club;
                $clubId = $validated['target_club_id'] ?? (int) $sourceMembership->club_id;
                $sourceMembershipIsActive = $sourceMembership->status === 'active';
                $yearsInSourceClub = $sourceMembership->start_date
                    ? Carbon::parse($sourceMembership->start_date)->diffInYears(now())
                    : null;
                $sameClubTransition = (int) $clubId === (int) $sourceMembership->club_id;
                $currentMonthlyFee = (float) $sourceMembership->monthly_fee;

                if (!$sameClubTransition) {
                    $hasMultipleClubs = true;
                }

                if ($age === null && $sourceMembership->account?->primaryHolder?->member?->birthdate) {
                    $age = Carbon::parse($sourceMembership->account->primaryHolder->member->birthdate)->age;
                }
            } elseif (!empty($validated['from_membership_type_id'])) {
                $fromMembershipType = MembershipType::find($validated['from_membership_type_id']);
                $sourceClubId = $validated['source_club_id'] ?? $fromMembershipType?->club_id;
                $sourceClub = $sourceClubId ? Club::find($sourceClubId) : null;
            }

            $membershipType = MembershipType::query()
                ->where('id', $validated['membership_type_id'])
                ->where('club_id', $clubId)
                ->first();

            if (!$membershipType) {
                throw ValidationException::withMessages([
                    'membership_type_id' => 'La membresía seleccionada no pertenece al club actual.',
                ]);
            }

            if ($sameClubTransition && (int) $membershipType->id === (int) $sourceMembership?->membership_type_id) {
                throw ValidationException::withMessages([
                    'membership_type_id' => 'Debes seleccionar un tipo de membresía distinto al actual para realizar el cambio.',
                ]);
            }

            if ($sourceClub && $fromMembershipType && $fromMembershipType->club_id !== $sourceClub->id) {
                throw ValidationException::withMessages([
                    'source_club_id' => 'La membresía de origen no pertenece al club de origen seleccionado.',
                ]);
            }

            if ($sourceMembership && !$sourceMembershipIsActive) {
                throw ValidationException::withMessages([
                    'source_membership_id' => $sameClubTransition
                        ? 'La membresía de origen debe estar activa para realizar el cambio.'
                        : 'La membresía de origen debe estar activa para generar una solicitud en el otro parque.',
                ]);
            }

            if ($this->shouldApplyAgeFilter($membershipType) && $age === null) {
                throw ValidationException::withMessages([
                    'age' => 'Captura la fecha de nacimiento del titular para calcular el precio de esta membresía.',
                ]);
            }

            $pricing = $this->resolveApplicablePricing(
                targetClubId: (int) $clubId,
                membershipType: $membershipType,
                fromMembershipType: $fromMembershipType,
                sourceClub: $sourceClub,
                age: $age,
                hasMultipleClubs: $hasMultipleClubs,
                sourceMembershipIsActive: $sourceMembershipIsActive,
                yearsInSourceClub: $yearsInSourceClub
            );

            return response()->json([
                'membership_type_id' => $membershipType->id,
                'membership_type_name' => $membershipType->name,
                'membership_type_code' => $membershipType->code,
                'monthly_fee' => (float) $pricing['monthly_fee'],
                'inscription_fee' => (float) ($pricing['inscription_fee'] ?? 0),
                'total_due' => (float) $pricing['monthly_fee'] + (float) ($pricing['inscription_fee'] ?? 0),
                'rule_type' => $pricing['rule_type'] ?? null,
                'source_membership_becomes_non_billable' => (bool) ($pricing['source_membership_becomes_non_billable'] ?? false),
                'current_monthly_fee' => $currentMonthlyFee,
                'additional_monthly_charge' => $this->resolveAdditionalMonthlyCharge(
                    currentMonthlyFee: $currentMonthlyFee,
                    newMonthlyFee: (float) $pricing['monthly_fee'],
                    sourceMembershipBecomesNonBillable: (bool) ($pricing['source_membership_becomes_non_billable'] ?? false)
                ),
                'charge_explanation' => $this->buildPricingPreviewExplanation(
                    currentMonthlyFee: $currentMonthlyFee,
                    newMonthlyFee: (float) $pricing['monthly_fee'],
                    inscriptionFee: (float) ($pricing['inscription_fee'] ?? 0),
                    sourceMembershipBecomesNonBillable: (bool) ($pricing['source_membership_becomes_non_billable'] ?? false),
                    sameClubTransition: $sameClubTransition
                ),
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return response()->json([
                'message' => collect($errors)->flatten()->first() ?? 'Ocurrió un error de validación.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al calcular el precio.',
                'exception' => $e->getMessage(),
            ], 500);
        }
    }

    public function createAdditionalMembership(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership = $this->loadMembershipContext($membership);

        if ($membership->status !== 'active') {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo puedes generar una solicitud para el otro parque a partir de una membresía activa.',
                'exception' => '',
            ]);
        }

        $targetClub = Club::query()
            ->where('id', '!=', $membership->club_id)
            ->orderBy('name')
            ->first();

        if (!$targetClub) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'No se encontró un parque destino disponible para esta solicitud.',
                'exception' => '',
            ]);
        }

        $targetMembershipTypes = MembershipType::query()
            ->where('show_in_listing', true)
            ->where('club_id', $targetClub->id)
            ->with([
                'documentTypes:id,name,allowed_extensions',
                'documentTypes.relationships:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Members/Create', [
            'membershipTypes' => $targetMembershipTypes,
            'originMembershipTypes' => collect(),
            'clubs' => collect([$targetClub]),
            ...$this->getCreateFormCatalogs(),
            'isCrossClubRequest' => true,
            'targetClub' => $targetClub,
            'sourceMembership' => $this->buildSourceMembershipPayload($membership),
            'prefillMembers' => $this->buildPrefillMembers($membership),
        ]);
    }

    public function createMembershipTransition(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership = $this->loadMembershipContext($membership);

        if ($membership->status !== 'active' || !$membership->is_primary) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo puedes cambiar una membresía activa y principal dentro de la misma cuenta.',
                'exception' => '',
            ]);
        }

        $targetMembershipTypes = MembershipType::query()
            ->where('show_in_listing', true)
            ->where('club_id', $membership->club_id)
            ->where('id', '!=', $membership->membership_type_id)
            ->with([
                'documentTypes:id,name,allowed_extensions',
                'documentTypes.relationships:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        if ($targetMembershipTypes->isEmpty()) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'No hay tipos de membresía disponibles para cambiar dentro de este parque.',
                'exception' => '',
            ]);
        }

        return Inertia::render('Members/Create', [
            'membershipTypes' => $targetMembershipTypes,
            'originMembershipTypes' => collect(),
            'clubs' => collect([$membership->club]),
            ...$this->getCreateFormCatalogs(),
            'isMembershipTransition' => true,
            'targetClub' => $membership->club,
            'sourceMembership' => $this->buildSourceMembershipPayload($membership),
            'prefillMembers' => $this->buildPrefillMembers($membership),
        ]);
    }

    public function show(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership = $this->loadMembershipContext($membership);

        if ($membership->status !== 'active' || !$membership->is_primary) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo puedes gestionar una membresía activa y principal.',
                'exception' => '',
            ]);
        }

        return Inertia::render('Members/Show', [
            'membership' => $this->buildSourceMembershipPayload($membership),
            'account' => $this->buildMembershipAccountPayload($membership),
            'canAddFamilyMembers' => (bool) $membership->membershipType?->allows_multiple_members,
            'canChangePrimaryHolder' => (bool) $membership->membershipType?->allows_multiple_members
                && $membership->account->accountMembers->count() > 1,
            'canSeparateMembers' => (bool) $membership->membershipType?->allows_multiple_members
                && $membership->account->accountMembers->where('is_primary_holder', false)->isNotEmpty(),
        ]);
    }

    public function createChangePrimaryHolder(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership = $this->loadMembershipContext($membership);

        if ($membership->status !== 'active' || !$membership->is_primary) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo puedes cambiar el titular de una membresía activa y principal.',
                'exception' => '',
            ]);
        }

        if (!$membership->membershipType?->allows_multiple_members) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo las membresías familiares permiten cambiar de titular.',
                'exception' => '',
            ]);
        }

        $currentPrimaryHolder = $membership->account?->primaryHolder;
        $candidates = $membership->account?->accountMembers
            ->where('is_primary_holder', false)
            ->values() ?? collect();

        if ($candidates->isEmpty()) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'No hay integrantes disponibles para asumir la titularidad.',
                'exception' => '',
            ]);
        }

        return Inertia::render('Members/ChangePrimaryHolder', [
            'membership' => $this->buildSourceMembershipPayload($membership),
            'currentPrimaryHolder' => $this->buildAccountMemberPayload($currentPrimaryHolder),
            'candidateMembers' => $candidates
                ->map(fn(MembershipAccountMember $accountMember) => $this->buildAccountMemberPayload($accountMember))
                ->values(),
        ]);
    }

    public function updatePrimaryHolder(Request $request, Membership $membership)
    {
        try {
            $clubId = session('club_id');

            if ((int) $membership->club_id !== (int) $clubId) {
                abort(404);
            }

            $membership = $this->loadMembershipContext($membership);

            if ($membership->status !== 'active' || !$membership->is_primary) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Solo puedes cambiar el titular de una membresía activa y principal.',
                    'exception' => '',
                ]);
            }

            if (!$membership->membershipType?->allows_multiple_members) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Solo las membresías familiares permiten cambiar de titular.',
                    'exception' => '',
                ]);
            }

            $validated = $request->validate([
                'new_primary_member_id' => ['required', new ExistsInSchema('members', 'members', 'id')],
                'reason' => ['nullable', 'string', 'max:255'],
            ]);

            $newPrimaryHolder = $membership->account->accountMembers
                ->firstWhere('member_id', (int) $validated['new_primary_member_id']);

            if (!$newPrimaryHolder) {
                throw ValidationException::withMessages([
                    'new_primary_member_id' => 'El integrante seleccionado no pertenece a esta cuenta.',
                ]);
            }

            if ($newPrimaryHolder->is_primary_holder) {
                throw ValidationException::withMessages([
                    'new_primary_member_id' => 'El integrante seleccionado ya es el titular actual.',
                ]);
            }

            $currentPrimaryHolder = $membership->account->primaryHolder;

            if (!$currentPrimaryHolder) {
                throw ValidationException::withMessages([
                    'new_primary_member_id' => 'No se encontró un titular actual para esta cuenta.',
                ]);
            }

            $reason = $validated['reason'] ?? 'Cambio de titular de la cuenta';
            $titularRelationshipId = Relationship::query()
                ->where('name', 'Titular')
                ->value('id');

            DB::transaction(function () use ($membership, $currentPrimaryHolder, $newPrimaryHolder, $reason, $titularRelationshipId) {
                $currentPrimaryHolder->update([
                    'is_primary_holder' => false,
                ]);

                $newPrimaryHolder->update([
                    'is_primary_holder' => true,
                    'relationship_id' => $titularRelationshipId ?: $newPrimaryHolder->relationship_id,
                ]);

                foreach ($membership->account->memberships()->where('status', 'active')->where('is_primary', true)->get() as $accountMembership) {
                    DB::table('memberships.membership_history')->insert([
                        'membership_id' => $accountMembership->id,
                        'old_membership_type_id' => $accountMembership->membership_type_id,
                        'new_membership_type_id' => $accountMembership->membership_type_id,
                        'changed_by' => auth()->id(),
                        'effective_date' => now()->toDateString(),
                        'reason' => $reason,
                        'previous_monthly_fee' => $accountMembership->monthly_fee,
                        'new_monthly_fee' => $accountMembership->monthly_fee,
                        'metadata' => json_encode([
                            'transition_kind' => 'primary_holder_changed',
                            'previous_primary_member_id' => $currentPrimaryHolder->member_id,
                            'new_primary_member_id' => $newPrimaryHolder->member_id,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            return redirect()->route('members.index')->with('success', 'El titular de la cuenta se actualizó correctamente.');
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al cambiar el titular de la cuenta.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function createFamilyMember(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership = $this->loadMembershipContext($membership);

        if ($membership->status !== 'active' || !$membership->is_primary) {
            return redirect()->route('members.manage.show', $membership)->withErrors([
                'messageError' => 'Solo puedes agregar familiares a una membresía activa y principal.',
                'exception' => '',
            ]);
        }

        if (!$membership->membershipType?->allows_multiple_members) {
            return redirect()->route('members.manage.show', $membership)->withErrors([
                'messageError' => 'Solo las membresías familiares permiten agregar familiares.',
                'exception' => '',
            ]);
        }

        return Inertia::render('Members/AddFamilyMember', [
            'membership' => $this->buildSourceMembershipPayload($membership),
            'account' => $this->buildMembershipAccountPayload($membership),
            ...$this->getCreateFormCatalogs(),
            'relationships' => Relationship::query()
                ->select('id', 'name')
                ->get()
                ->reject(fn(Relationship $relationship) => $this->isTitularRelationship($relationship->name))
                ->values(),
        ]);
    }

    public function storeFamilyMember(Request $request, Membership $membership)
    {
        try {
            $clubId = session('club_id');

            if ((int) $membership->club_id !== (int) $clubId) {
                abort(404);
            }

            $membership = $this->loadMembershipContext($membership);

            if ($membership->status !== 'active' || !$membership->is_primary) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Solo puedes agregar familiares a una membresía activa y principal.',
                    'exception' => '',
                ]);
            }

            if (!$membership->membershipType?->allows_multiple_members) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Solo las membresías familiares permiten agregar familiares.',
                    'exception' => '',
                ]);
            }

            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'second_last_name' => ['nullable', 'string', 'max:255'],
                'birthdate' => ['required', 'date', 'before_or_equal:today'],
                'birth_place' => ['nullable', 'string', 'max:255'],
                'birth_country_id' => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'birth_state_id' => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'birth_city_id' => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'city' => ['nullable', 'string', 'max:255'],
                'state' => ['nullable', 'string', 'max:255'],
                'nationality_id' => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'marital_status_id' => ['nullable', new ExistsInSchema('catalogs', 'marital_statuses', 'id')],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'occupation' => ['nullable', 'string', 'max:255'],
                'school_name' => ['nullable', 'string', 'max:255'],
                'relationship_id' => ['required', new ExistsInSchema('catalogs', 'relationships', 'id')],
                'address' => ['nullable', 'array'],
                'address.street' => ['nullable', 'string', 'max:255'],
                'address.neighborhood' => ['nullable', 'string', 'max:255'],
                'address.postal_code' => ['nullable', 'string', 'max:10'],
                'address.country_id' => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'address.state_id' => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'address.city_id' => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'address.city' => ['nullable', 'string', 'max:255'],
                'address.state' => ['nullable', 'string', 'max:255'],
                'address.country' => ['nullable', 'string', 'max:255'],
                'address.years_in_city' => ['nullable', 'integer', 'min:0', 'max:999'],
                'employment' => ['nullable', 'array'],
                'employment.company_name' => ['nullable', 'string', 'max:255'],
                'employment.company_address' => ['nullable', 'string', 'max:255'],
                'employment.company_phone' => ['nullable', 'string', 'max:50'],
            ]);

            $relationship = Relationship::query()->findOrFail($validated['relationship_id']);
            $age = Carbon::parse($validated['birthdate'])->age;

            if ($this->isTitularRelationship($relationship->name)) {
                throw ValidationException::withMessages([
                    'relationship_id' => 'No puedes agregar otro integrante con parentesco de titular.',
                ]);
            }

            if ($this->isChildRelationship($relationship->name) && $age >= 24) {
                throw ValidationException::withMessages([
                    'birthdate' => 'Los hijos no pueden ser mayores de 24 años.',
                ]);
            }

            if ($this->isSpouseRelationship($relationship->name) && $this->membershipAccountHasSpouse($membership)) {
                throw ValidationException::withMessages([
                    'relationship_id' => 'La cuenta familiar ya cuenta con un cónyuge registrado.',
                ]);
            }

            DB::transaction(function () use ($validated, $membership, $relationship) {
                $birthLocationAttributes = $this->resolveBirthLocationFields(
                    $validated,
                    'birth_country_id',
                    'birth_state_id',
                    'birth_city_id'
                );
                $addressLocationAttributes = $this->resolveAddressLocationFields(
                    $validated['address'] ?? [],
                    'address.country_id',
                    'address.state_id',
                    'address.city_id'
                );

                $member = Member::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'second_last_name' => $validated['second_last_name'] ?? null,
                    'birthdate' => $validated['birthdate'],
                    'nationality_id' => $validated['nationality_id'] ?? null,
                    'marital_status_id' => $validated['marital_status_id'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'occupation' => $validated['occupation'] ?? null,
                    'school_name' => $validated['school_name'] ?? null,
                    ...$birthLocationAttributes,
                ]);

                if ($this->hasFilledValues($validated['address'] ?? [])) {
                    Address::create([
                        'member_id' => $member->id,
                        'is_primary' => true,
                        'street' => $validated['address']['street'] ?? null,
                        'neighborhood' => $validated['address']['neighborhood'] ?? null,
                        'postal_code' => $validated['address']['postal_code'] ?? null,
                        'years_in_city' => $validated['address']['years_in_city'] ?? null,
                        ...$addressLocationAttributes,
                    ]);
                }

                if ($this->hasFilledValues($validated['employment'] ?? [])) {
                    EmploymentInfo::create([
                        'member_id' => $member->id,
                        'company_name' => $validated['employment']['company_name'] ?? null,
                        'company_address' => $validated['employment']['company_address'] ?? null,
                        'company_phone' => $validated['employment']['company_phone'] ?? null,
                    ]);
                }

                MembershipAccountMember::create([
                    'membership_account_id' => $membership->membership_account_id,
                    'member_id' => $member->id,
                    'relationship_id' => $relationship->id,
                    'is_primary_holder' => false,
                ]);
            });

            return redirect()
                ->route('members.manage.show', $membership)
                ->with('success', 'El familiar se agrego correctamente a la cuenta.');
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al agregar al familiar.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function createMemberSeparation(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership = $this->loadMembershipContext($membership);

        if ($membership->status !== 'active' || !$membership->is_primary) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo puedes separar integrantes desde una membresía activa y principal.',
                'exception' => '',
            ]);
        }

        if (!$membership->membershipType?->allows_multiple_members) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo las membresías familiares permiten separar integrantes.',
                'exception' => '',
            ]);
        }

        $candidateMembers = $this->buildSeparationCandidateMembers($membership);

        if ($candidateMembers->isEmpty()) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'No hay integrantes elegibles para separarse en una cuenta nueva.',
                'exception' => '',
            ]);
        }

        return Inertia::render('Members/SeparateMember', [
            'membership' => $this->buildSourceMembershipPayload($membership),
            'candidateMembers' => $candidateMembers->values(),
        ]);
    }

    public function storeMemberSeparation(Request $request, Membership $membership)
    {
        try {
            $clubId = session('club_id');

            if ((int) $membership->club_id !== (int) $clubId) {
                abort(404);
            }

            $membership = $this->loadMembershipContext($membership);

            if ($membership->status !== 'active' || !$membership->is_primary) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Solo puedes separar integrantes desde una membresía activa y principal.',
                    'exception' => '',
                ]);
            }

            if (!$membership->membershipType?->allows_multiple_members) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Solo las membresías familiares permiten separar integrantes.',
                    'exception' => '',
                ]);
            }

            $validated = $request->validate([
                'member_id' => ['required', new ExistsInSchema('members', 'members', 'id')],
                'target_membership_type_id' => ['required', new ExistsInSchema('memberships', 'types', 'id')],
                'reason' => ['nullable', 'string', 'max:255'],
            ]);

            $accountMember = $membership->account->accountMembers
                ->firstWhere('member_id', (int) $validated['member_id']);

            if (!$accountMember) {
                throw ValidationException::withMessages([
                    'member_id' => 'El integrante seleccionado no pertenece a esta cuenta.',
                ]);
            }

            if ($accountMember->is_primary_holder) {
                throw ValidationException::withMessages([
                    'member_id' => 'No puedes separar al titular actual con este flujo.',
                ]);
            }

            $separationOptions = $this->buildSeparationTargetOptions($membership, $accountMember);
            $selectedTargetOption = $separationOptions
                ->firstWhere('id', (int) $validated['target_membership_type_id']);

            if (!$selectedTargetOption) {
                throw ValidationException::withMessages([
                    'target_membership_type_id' => 'La membresía destino seleccionada no aplica para este integrante.',
                ]);
            }

            if ($this->memberHasActivePrimaryMembershipInClub($accountMember->member_id, $membership->club_id)) {
                throw ValidationException::withMessages([
                    'member_id' => 'El integrante ya cuenta con una membresía activa propia en este club.',
                ]);
            }

            $targetMembershipType = MembershipType::findOrFail($validated['target_membership_type_id']);
            $titularRelationshipId = Relationship::query()
                ->where('name', 'Titular')
                ->value('id');
            $reason = $validated['reason'] ?? 'Separación de integrante a cuenta nueva';

            DB::transaction(function () use ($membership, $accountMember, $targetMembershipType, $selectedTargetOption, $titularRelationshipId, $reason) {
                $newAccount = $this->createMembershipAccount(
                    club: $membership->club,
                    accountType: $targetMembershipType->allows_multiple_members ? 'family' : 'individual',
                    status: 'active'
                );

                MembershipAccountMember::create([
                    'membership_account_id' => $newAccount->id,
                    'member_id' => $accountMember->member_id,
                    'relationship_id' => $titularRelationshipId ?: $accountMember->relationship_id,
                    'is_primary_holder' => true,
                ]);

                $newMembership = Membership::create([
                    'membership_account_id' => $newAccount->id,
                    'club_id' => $membership->club_id,
                    'membership_type_id' => $targetMembershipType->id,
                    'origin_membership_type_id' => $membership->membership_type_id,
                    'is_primary' => true,
                    'is_billable' => true,
                    'monthly_fee' => $selectedTargetOption['monthly_fee'],
                    'start_date' => now()->toDateString(),
                    'end_date' => $targetMembershipType->validity_months
                        ? now()->addMonthsNoOverflow($targetMembershipType->validity_months)->toDateString()
                        : null,
                    'status' => 'active',
                ]);

                $newMembership->load(['membershipType', 'account.primaryHolder']);

                $this->membershipChargeService->createInitialCharges(
                    membership: $newMembership,
                    monthlyFee: (float) $selectedTargetOption['monthly_fee'],
                    inscriptionFee: (float) ($selectedTargetOption['inscription_fee'] ?? 0),
                    metadata: [
                        'charge_origin' => 'member_separation',
                        'source_membership_id' => $membership->id,
                    ],
                    chargeDate: now()
                );

                $accountMember->delete();

                DB::table('memberships.membership_history')->insert([
                    'membership_id' => $newMembership->id,
                    'old_membership_type_id' => $membership->membership_type_id,
                    'new_membership_type_id' => $targetMembershipType->id,
                    'changed_by' => auth()->id(),
                    'effective_date' => now()->toDateString(),
                    'reason' => $reason,
                    'previous_monthly_fee' => null,
                    'new_monthly_fee' => $selectedTargetOption['monthly_fee'],
                    'metadata' => json_encode([
                        'transition_kind' => 'member_separation',
                        'source_membership_id' => $membership->id,
                        'source_membership_account_id' => $membership->membership_account_id,
                        'member_id' => $accountMember->member_id,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return redirect()->route('members.index')->with('success', 'El integrante fue separado correctamente en una nueva cuenta.');
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrio un error al separar al integrante.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $sessionClubId = session('club_id');

            if (!$sessionClubId) {
                return redirect()->back()->withErrors([
                    'messageError' => 'No hay un club seleccionado en la sesion.',
                    'exception' => '',
                ]);
            }

            $validated = $request->validate([
                'source_membership_id' => ['nullable', new ExistsInSchema('memberships', 'memberships', 'id')],
                'target_club_id' => ['nullable', new ExistsInSchema('clubs', 'clubs', 'id')],
                'membership_type_id' => ['required', new ExistsInSchema('memberships', 'types', 'id')],
                'from_membership_type_id' => ['nullable', new ExistsInSchema('memberships', 'types', 'id')],
                'source_club_id' => ['nullable', new ExistsInSchema('clubs', 'clubs', 'id')],
                'has_multiple_clubs' => ['nullable', 'boolean'],
                'source_membership_is_active' => ['nullable', 'boolean'],
                'years_in_source_club' => ['nullable', 'integer', 'min:0', 'max:99'],
                'members' => ['required', 'array', 'min:1'],
                'members.*.id' => ['nullable', new ExistsInSchema('members', 'members', 'id')],
                'members.*.first_name' => ['required', 'string', 'max:255'],
                'members.*.last_name' => ['required', 'string', 'max:255'],
                'members.*.second_last_name' => ['nullable', 'string', 'max:255'],
                'members.*.birthdate' => ['nullable', 'date'],
                'members.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
                'members.*.birth_place' => ['nullable', 'string', 'max:255'],
                'members.*.birth_country_id' => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'members.*.birth_state_id' => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'members.*.birth_city_id' => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'members.*.city' => ['nullable', 'string', 'max:255'],
                'members.*.state' => ['nullable', 'string', 'max:255'],
                'members.*.nationality_id' => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'members.*.marital_status_id' => ['nullable', new ExistsInSchema('catalogs', 'marital_statuses', 'id')],
                'members.*.phone' => ['nullable', 'string', 'max:50'],
                'members.*.email' => ['nullable', 'email', 'max:255'],
                'members.*.occupation' => ['nullable', 'string', 'max:255'],
                'members.*.school_name' => ['nullable', 'string', 'max:255'],
                'members.*.relationship_id' => ['nullable', new ExistsInSchema('catalogs', 'relationships', 'id')],
                'members.*.relationship_name' => ['nullable', 'string', 'max:255'],
                'members.*.is_primary_holder' => ['required', 'boolean'],
                'members.*.address' => ['nullable', 'array'],
                'members.*.address.street' => ['nullable', 'string', 'max:255'],
                'members.*.address.neighborhood' => ['nullable', 'string', 'max:255'],
                'members.*.address.postal_code' => ['nullable', 'string', 'max:10'],
                'members.*.address.country_id' => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'members.*.address.state_id' => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'members.*.address.city_id' => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'members.*.address.city' => ['nullable', 'string', 'max:255'],
                'members.*.address.state' => ['nullable', 'string', 'max:255'],
                'members.*.address.country' => ['nullable', 'string', 'max:255'],
                'members.*.address.years_in_city' => ['nullable', 'integer', 'min:0', 'max:999'],
                'members.*.employment' => ['nullable', 'array'],
                'members.*.employment.company_name' => ['nullable', 'string', 'max:255'],
                'members.*.employment.company_address' => ['nullable', 'string', 'max:255'],
                'members.*.employment.company_phone' => ['nullable', 'string', 'max:50'],
            ]);

            $clubId = $validated['target_club_id'] ?? $sessionClubId;
            $sourceMembership = null;
            $fromMembershipType = null;
            $sourceClub = null;
            $hasMultipleClubs = (bool) ($validated['has_multiple_clubs'] ?? false);
            $sourceMembershipIsActive = (bool) ($validated['source_membership_is_active'] ?? false);
            $yearsInSourceClub = array_key_exists('years_in_source_club', $validated)
                && $validated['years_in_source_club'] !== null
                ? (int) $validated['years_in_source_club']
                : null;
            $sameClubTransition = false;

            if (!empty($validated['source_membership_id'])) {
                $sourceMembership = Membership::query()
                    ->with([
                        'membershipType',
                        'club',
                        'account.primaryHolder.member',
                        'account.accountMembers',
                    ])
                    ->findOrFail($validated['source_membership_id']);

                $fromMembershipType = $sourceMembership->membershipType;
                $sourceClub = $sourceMembership->club;
                $clubId = $validated['target_club_id'] ?? (int) $sourceMembership->club_id;
                $sourceMembershipIsActive = $sourceMembership->status === 'active';
                $yearsInSourceClub = $sourceMembership->start_date
                    ? Carbon::parse($sourceMembership->start_date)->diffInYears(now())
                    : null;
                $sameClubTransition = (int) $clubId === (int) $sourceMembership->club_id;

                if (!$sameClubTransition) {
                    $hasMultipleClubs = true;
                }
            } elseif (!empty($validated['from_membership_type_id'])) {
                $fromMembershipType = MembershipType::find($validated['from_membership_type_id']);
                $sourceClubId = $validated['source_club_id'] ?? $fromMembershipType?->club_id;
                $sourceClub = $sourceClubId ? Club::find($sourceClubId) : null;
            }

            $membershipType = MembershipType::where('id', $validated['membership_type_id'])
                ->where('club_id', $clubId)
                ->first();

            if (!$membershipType) {
                throw ValidationException::withMessages([
                    'membership_type_id' => 'La membresía seleccionada no pertenece al club actual.',
                ]);
            }

            if ($sameClubTransition && (int) $membershipType->id === (int) $sourceMembership?->membership_type_id) {
                throw ValidationException::withMessages([
                    'membership_type_id' => 'Debes seleccionar un tipo de membresía distinto al actual para realizar el cambio.',
                ]);
            }

            if ($sourceClub && $fromMembershipType && $fromMembershipType->club_id !== $sourceClub->id) {
                return redirect()->back()->withErrors([
                    'messageError' => 'La membresía de origen no pertenece al club de origen seleccionado.',
                    'exception' => '',
                ]);
            }

            if ($sourceMembership && !$sourceMembershipIsActive) {
                return redirect()->back()->withErrors([
                    'messageError' => $sameClubTransition
                        ? 'La membresía de origen debe estar activa para realizar el cambio.'
                        : 'La membresía de origen debe estar activa para generar una solicitud en el otro parque.',
                    'exception' => '',
                ]);
            }

            if ($sourceMembership) {
                $sourcePrimaryHolderId = $sourceMembership->account?->primaryHolder?->member_id;
                $sourceAccountMemberIds = $sourceMembership->account?->accountMembers
                    ? $sourceMembership->account->accountMembers->pluck('member_id')->map(fn ($id) => (int) $id)->all()
                    : [];
                $requestedPrimaryHolderId = collect($validated['members'])
                    ->firstWhere('is_primary_holder', true)['id'] ?? null;

                if ($sourcePrimaryHolderId && (int) $requestedPrimaryHolderId !== (int) $sourcePrimaryHolderId) {
                    return redirect()->back()->withErrors([
                        'messageError' => 'El titular de la nueva solicitud debe coincidir con el titular de la membresía origen.',
                        'exception' => '',
                    ]);
                }

                if (!$sameClubTransition) {
                    $existingTargetClubMembership = Membership::query()
                        ->where('club_id', $clubId)
                        ->whereHas('account.primaryHolder', function (Builder $builder) use ($sourcePrimaryHolderId) {
                            $builder->where('member_id', $sourcePrimaryHolderId);
                        })
                        ->exists();

                    if ($existingTargetClubMembership) {
                        return redirect()->back()->withErrors([
                            'messageError' => 'El titular ya tiene una cuenta registrada en el club destino.',
                            'exception' => '',
                        ]);
                    }
                }

                foreach ($validated['members'] as $index => $memberData) {
                    if (!empty($memberData['id']) && !in_array((int) $memberData['id'], $sourceAccountMemberIds, true)) {
                        return redirect()->back()->withErrors([
                            'messageError' => "El integrante seleccionado en la posición " . ($index + 1) . " no pertenece a la cuenta origen.",
                            'exception' => '',
                        ]);
                    }
                }
            }

            $primaryMembers = collect($validated['members'])
                ->where('is_primary_holder', true)
                ->values();

            $submittedExistingMemberIds = collect($validated['members'])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id);

            if ($submittedExistingMemberIds->count() !== $submittedExistingMemberIds->unique()->count()) {
                return redirect()->back()->withErrors([
                    'messageError' => 'No puedes incluir al mismo integrante más de una vez en la solicitud.',
                    'exception' => '',
                ]);
            }

            if ($primaryMembers->count() !== 1) {
                // throw ValidationException::withMessages([
                //     'members' => 'Debe existir exactamente un titular en la solicitud.',
                // ]);
                return redirect()->back()->withErrors([
                    'messageError' => 'Debe existir exactamente un titular en la solicitud.',
                    'exception' => '',
                ]);
            }

            if (!$membershipType->allows_multiple_members && count($validated['members']) > 1) {
                // throw ValidationException::withMessages([
                //     'members' => 'La membresia seleccionada no permite multiples integrantes.',
                // ]);
                return redirect()->back()->withErrors([
                    'messageError' => 'La membresía seleccionada no permite multiples integrantes.',
                    'exception' => '',
                ]);
            }

            foreach ($validated['members'] as $index => $memberData) {
                if (empty($memberData['is_primary_holder']) && empty($memberData['relationship_id'])) {
                    // throw ValidationException::withMessages([
                    //     "members.$index.relationship_id" => 'El parentesco es obligatorio para familiares.',
                    // ]);
                    return redirect()->back()->withErrors([
                        'messageError' => 'El parentesco es obligatorio para familiares.',
                        'exception' => '',
                    ]);
                }
            }

            $primaryMember = $primaryMembers->first();
            $primaryAge = $this->resolveAge($primaryMember);

            if ($membershipType->requires_origin_family && !$fromMembershipType) {
                // throw ValidationException::withMessages([
                //     'from_membership_type_id' => 'La membresia seleccionada requiere una membresia familiar de origen.',
                // ]);
                return redirect()->back()->withErrors([
                    'messageError' => 'La membresía seleccionada requiere una membresía familiar de origen.',
                    'exception' => '',
                ]);
            }

            if ($membershipType->requires_origin_family && !$fromMembershipType->allows_multiple_members) {
                // throw ValidationException::withMessages([
                //     'from_membership_type_id' => 'La membresia seleccionada debe provenir de una membresia familiar.',
                // ]);
                return redirect()->back()->withErrors([
                    'messageError' => 'La membresía seleccionada debe provenir de una membresía familiar.',
                    'exception' => '',
                ]);
            }

            $pricing = $this->resolveApplicablePricing(
                targetClubId: $clubId,
                membershipType: $membershipType,
                fromMembershipType: $fromMembershipType,
                sourceClub: $sourceClub,
                age: $primaryAge,
                hasMultipleClubs: $hasMultipleClubs,
                sourceMembershipIsActive: $sourceMembershipIsActive,
                yearsInSourceClub: $yearsInSourceClub
            );

            $club = Club::findOrFail($clubId);
            $successMessage = $sameClubTransition
                ? 'La membresía se actualizó correctamente dentro de la misma cuenta.'
                : ($sourceMembership
                    ? 'La membresía del otro parque se agregó correctamente a la misma cuenta.'
                    : 'La cuenta de membresía y sus integrantes se registraron correctamente.');

            $sourceAccountMembersById = $sourceMembership?->account?->accountMembers
                ? $sourceMembership->account->accountMembers->keyBy('member_id')
                : collect();

            DB::transaction(function () use ($validated, $membershipType, $pricing, $clubId, $club, $fromMembershipType, $sourceMembership, $sameClubTransition, $sourceAccountMembersById) {
                $sourceAccount = $sourceMembership?->account;

                $membershipAccount = $sameClubTransition
                    ? tap($sourceAccount)->update([
                        'account_type' => $membershipType->allows_multiple_members ? 'family' : 'individual',
                        'status' => 'active',
                    ])
                    : $this->createMembershipAccount(
                        club: $club,
                        accountType: $membershipType->allows_multiple_members ? 'family' : 'individual',
                        status: 'pending',
                        accountGroup: $sourceAccount?->accountGroup
                    );

                $submittedMemberIds = [];

                foreach ($validated['members'] as $index => $memberData) {
                    $birthLocationAttributes = $this->resolveBirthLocationFields(
                        $memberData,
                        "members.$index.birth_country_id",
                        "members.$index.birth_state_id",
                        "members.$index.birth_city_id"
                    );
                    $addressLocationAttributes = $this->resolveAddressLocationFields(
                        $memberData['address'] ?? [],
                        "members.$index.address.country_id",
                        "members.$index.address.state_id",
                        "members.$index.address.city_id"
                    );

                    $memberAttributes = [
                        'first_name' => $memberData['first_name'],
                        'last_name' => $memberData['last_name'],
                        'second_last_name' => $memberData['second_last_name'] ?? null,
                        'birthdate' => $memberData['birthdate'] ?? null,
                        'nationality_id' => $memberData['nationality_id'] ?? null,
                        'marital_status_id' => $memberData['marital_status_id'] ?? null,
                        'phone' => $memberData['phone'] ?? null,
                        'email' => $memberData['email'] ?? null,
                        'occupation' => $memberData['occupation'] ?? null,
                        'school_name' => $memberData['school_name'] ?? null,
                        ...$birthLocationAttributes,
                    ];

                    $existingMember = !empty($memberData['id'])
                        ? Member::findOrFail($memberData['id'])
                        : null;

                    if ($existingMember && $sourceAccountMembersById->has($existingMember->id)) {
                        $memberAttributes = array_merge($memberAttributes, [
                            'first_name' => $existingMember->first_name,
                            'last_name' => $existingMember->last_name,
                            'second_last_name' => $existingMember->second_last_name,
                            'birthdate' => $existingMember->birthdate,
                            'birth_place' => $existingMember->birth_place,
                            'birth_country_id' => $existingMember->birth_country_id,
                            'state' => $existingMember->state,
                            'birth_state_id' => $existingMember->birth_state_id,
                            'city' => $existingMember->city,
                            'birth_city_id' => $existingMember->birth_city_id,
                            'nationality_id' => $existingMember->nationality_id,
                            'marital_status_id' => $existingMember->marital_status_id,
                        ]);
                    }

                    $member = $existingMember
                        ? tap($existingMember)->update($memberAttributes)
                        : Member::create($memberAttributes);

                    $submittedMemberIds[] = $member->id;

                    if ($this->hasFilledValues($memberData['address'] ?? [])) {
                        Address::updateOrCreate([
                            'member_id' => $member->id,
                            'is_primary' => true,
                        ], [
                            'street' => $memberData['address']['street'] ?? null,
                            'neighborhood' => $memberData['address']['neighborhood'] ?? null,
                            'postal_code' => $memberData['address']['postal_code'] ?? null,
                            'years_in_city' => $memberData['address']['years_in_city'] ?? null,
                            ...$addressLocationAttributes,
                        ]);
                    }

                    if ($this->hasFilledValues($memberData['employment'] ?? [])) {
                        EmploymentInfo::updateOrCreate([
                            'member_id' => $member->id,
                        ], [
                            'company_name' => $memberData['employment']['company_name'] ?? null,
                            'company_address' => $memberData['employment']['company_address'] ?? null,
                            'company_phone' => $memberData['employment']['company_phone'] ?? null,
                        ]);
                    }

                    $accountMemberAttributes = [
                        'relationship_id' => $sourceAccountMembersById->has($member->id)
                            ? $sourceAccountMembersById->get($member->id)?->relationship_id
                            : ($memberData['relationship_id'] ?? null),
                        'is_primary_holder' => $memberData['is_primary_holder'],
                    ];

                    if ($sameClubTransition) {
                        MembershipAccountMember::updateOrCreate([
                            'membership_account_id' => $membershipAccount->id,
                            'member_id' => $member->id,
                        ], $accountMemberAttributes);
                    } else {
                        MembershipAccountMember::create([
                            'membership_account_id' => $membershipAccount->id,
                            'member_id' => $member->id,
                            ...$accountMemberAttributes,
                        ]);
                    }
                }

                if ($sameClubTransition) {
                    MembershipAccountMember::query()
                        ->where('membership_account_id', $membershipAccount->id)
                        ->whereNotIn('member_id', $submittedMemberIds)
                        ->delete();

                    $previousMembershipTypeId = $sourceMembership->membership_type_id;
                    $previousMonthlyFee = (float) $sourceMembership->monthly_fee;
                    $previousBillableState = (bool) $sourceMembership->is_billable;

                    $sourceMembership->update([
                        'membership_type_id' => $membershipType->id,
                        'origin_membership_type_id' => $fromMembershipType?->id,
                        'is_primary' => true,
                        'is_billable' => $previousBillableState,
                        'monthly_fee' => $pricing['monthly_fee'],
                        'start_date' => now()->toDateString(),
                        'end_date' => $membershipType->validity_months
                            ? now()->addMonthsNoOverflow($membershipType->validity_months)->toDateString()
                            : null,
                        'status' => 'active',
                    ]);

                    DB::table('memberships.membership_history')->insert([
                        'membership_id' => $sourceMembership->id,
                        'old_membership_type_id' => $previousMembershipTypeId,
                        'new_membership_type_id' => $membershipType->id,
                        'changed_by' => auth()->id(),
                        'effective_date' => now()->toDateString(),
                        'reason' => 'Cambio de tipo de membresia',
                        'previous_monthly_fee' => $previousMonthlyFee,
                        'new_monthly_fee' => $pricing['monthly_fee'],
                        'metadata' => json_encode([
                            'transition_kind' => 'same_account',
                            'previous_membership_type_id' => $previousMembershipTypeId,
                            'new_membership_type_id' => $membershipType->id,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $sourceMembership->load(['membershipType', 'account.primaryHolder']);

                    $this->membershipChargeService->createInitialCharges(
                        membership: $sourceMembership,
                        monthlyFee: (float) $pricing['monthly_fee'],
                        inscriptionFee: (float) ($pricing['inscription_fee'] ?? 0),
                        metadata: [
                            'charge_origin' => 'same_account_transition',
                            'previous_membership_type_id' => $previousMembershipTypeId,
                            'new_membership_type_id' => $membershipType->id,
                        ],
                        chargeDate: now(),
                        reconcileExistingMonthlyCharge: true
                    );

                    return;
                }

                $newMembership = Membership::create([
                    'membership_account_id' => $membershipAccount->id,
                    'club_id' => $clubId,
                    'membership_type_id' => $membershipType->id,
                    'origin_membership_type_id' => $fromMembershipType?->id,
                    'is_primary' => true,
                    'is_billable' => true,
                    'monthly_fee' => $pricing['monthly_fee'],
                    'start_date' => now()->toDateString(),
                    'end_date' => $membershipType->validity_months
                        ? now()->addMonthsNoOverflow($membershipType->validity_months)->toDateString()
                        : null,
                    'status' => 'active',
                ]);

                $newMembership->load(['membershipType', 'account.primaryHolder']);

                $this->membershipChargeService->createInitialCharges(
                    membership: $newMembership,
                    monthlyFee: (float) $pricing['monthly_fee'],
                    inscriptionFee: (float) ($pricing['inscription_fee'] ?? 0),
                    metadata: [
                        'charge_origin' => $sourceMembership ? 'additional_membership' : 'membership_registration',
                        'source_membership_id' => $sourceMembership?->id,
                    ],
                    chargeDate: now(),
                    reconcileExistingMonthlyCharge: (bool) ($sourceMembership && ($pricing['source_membership_becomes_non_billable'] ?? false))
                );

                if ($sourceMembership && ($pricing['source_membership_becomes_non_billable'] ?? false)) {
                    $sourceMembership->update([
                        'is_billable' => false,
                    ]);
                }
            });

            return redirect()
                ->back()
                ->with('success', $successMessage);
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al guardar la membresía y sus integrantes.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, string $id)
    {
        //$validated = $request->validate([
        //    'field1' => 'required|string|max:255',
        //    'field2' => 'required|email|unique:table,column,' . $id,
        //]);

        //Model::where('column', $id)->update([
        //    'field1' => $validated['field1'],
        //    'field2' => $validated['field2'],
        //]);

        return redirect()->back()->with('success', 'Message');
    }

    public function destroy(string $id)
    {
        return redirect()->back()->with('success', 'Message');
    }

    protected function loadMembershipContext(Membership $membership): Membership
    {
        $membership->load([
            'account.primaryHolder.member.primaryAddress',
            'account.primaryHolder.member.primaryAddress.country',
            'account.primaryHolder.member.primaryAddress.state',
            'account.primaryHolder.member.primaryAddress.city',
            'account.primaryHolder.member.employmentInfo',
            'account.primaryHolder.member.nationality',
            'account.primaryHolder.member.birthCountry',
            'account.primaryHolder.member.birthState',
            'account.primaryHolder.member.birthCity',
            'account.primaryHolder.member.maritalStatus',
            'account.accountMembers.relationship',
            'account.accountMembers.member.primaryAddress',
            'account.accountMembers.member.primaryAddress.country',
            'account.accountMembers.member.primaryAddress.state',
            'account.accountMembers.member.primaryAddress.city',
            'account.accountMembers.member.employmentInfo',
            'account.accountMembers.member.nationality',
            'account.accountMembers.member.birthCountry',
            'account.accountMembers.member.birthState',
            'account.accountMembers.member.birthCity',
            'account.accountMembers.member.maritalStatus',
            'membershipType',
            'club',
            'account.memberships.membershipType',
            'account.memberships.club',
        ]);

        return $membership;
    }

    protected function getCreateFormCatalogs(): array
    {
        $countries = Country::select('id', 'iso2 as code', 'name', 'demonym')
            ->orderBy('name')
            ->get();

        return [
            'relationships' => Relationship::select('id', 'name')->get(),
            'countries' => $countries,
            'nationalities' => $countries,
            'maritalStatuses' => MaritalStatus::select('id', 'code', 'name')
                ->orderBy('name')
                ->get(),
        ];
    }

    protected function buildPrefillMembers(Membership $membership)
    {
        return $membership->account->accountMembers
            ->sortByDesc('is_primary_holder')
            ->map(function (MembershipAccountMember $accountMember) {
                $member = $accountMember->member;
                $address = $member?->primaryAddress;
                $employment = $member?->employmentInfo;

                return [
                    'id' => $member?->id,
                    'first_name' => $member?->first_name,
                    'last_name' => $member?->last_name,
                    'second_last_name' => $member?->second_last_name,
                    'birthdate' => $member?->birthdate,
                    'birth_place' => $member?->birthCountry?->name ?? $member?->birth_place,
                    'birth_country_id' => $member?->birth_country_id,
                    'state' => $member?->birthState?->name ?? $member?->state,
                    'birth_state_id' => $member?->birth_state_id,
                    'city' => $member?->birthCity?->name ?? $member?->city,
                    'birth_city_id' => $member?->birth_city_id,
                    'is_from_source_membership' => true,
                    'nationality_id' => $member?->nationality_id,
                    'marital_status_id' => $member?->marital_status_id,
                    'phone' => $member?->phone,
                    'email' => $member?->email,
                    'occupation' => $member?->occupation,
                    'school_name' => $member?->school_name,
                    'relationship_id' => $accountMember->relationship_id,
                    'relationship_name' => $accountMember->relationship?->name,
                    'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                    'address' => [
                        'street' => $address?->street,
                        'neighborhood' => $address?->neighborhood,
                        'postal_code' => $address?->postal_code,
                        'country' => $address?->country?->name ?? $address?->country,
                        'country_id' => $address?->country_id,
                        'state' => $address?->state?->name ?? $address?->state,
                        'state_id' => $address?->state_id,
                        'city' => $address?->city?->name ?? $address?->city,
                        'city_id' => $address?->city_id,
                        'years_in_city' => $address?->years_in_city,
                    ],
                    'employment' => [
                        'company_name' => $employment?->company_name,
                        'company_address' => $employment?->company_address,
                        'company_phone' => $employment?->company_phone,
                    ],
                ];
            })
            ->values();
    }

    protected function resolveBirthLocationFields(
        array $payload,
        string $countryAttribute,
        string $stateAttribute,
        string $cityAttribute
    ): array {
        [$country, $state, $city] = $this->resolveLocationSelection(
            countryId: isset($payload['birth_country_id']) ? (int) $payload['birth_country_id'] : null,
            stateId: isset($payload['birth_state_id']) ? (int) $payload['birth_state_id'] : null,
            cityId: isset($payload['birth_city_id']) ? (int) $payload['birth_city_id'] : null,
            countryAttribute: $countryAttribute,
            stateAttribute: $stateAttribute,
            cityAttribute: $cityAttribute
        );

        return [
            'birth_place' => $country?->name ?? ($payload['birth_place'] ?? null),
            'birth_country_id' => $country?->id,
            'state' => $state?->name ?? ($payload['state'] ?? null),
            'birth_state_id' => $state?->id,
            'city' => $city?->name ?? ($payload['city'] ?? null),
            'birth_city_id' => $city?->id,
        ];
    }

    protected function resolveAddressLocationFields(
        array $payload,
        string $countryAttribute,
        string $stateAttribute,
        string $cityAttribute
    ): array {
        [$country, $state, $city] = $this->resolveLocationSelection(
            countryId: isset($payload['country_id']) ? (int) $payload['country_id'] : null,
            stateId: isset($payload['state_id']) ? (int) $payload['state_id'] : null,
            cityId: isset($payload['city_id']) ? (int) $payload['city_id'] : null,
            countryAttribute: $countryAttribute,
            stateAttribute: $stateAttribute,
            cityAttribute: $cityAttribute
        );

        return [
            'country' => $country?->name ?? ($payload['country'] ?? null),
            'country_id' => $country?->id,
            'state' => $state?->name ?? ($payload['state'] ?? null),
            'state_id' => $state?->id,
            'city' => $city?->name ?? ($payload['city'] ?? null),
            'city_id' => $city?->id,
        ];
    }

    protected function resolveLocationSelection(
        ?int $countryId,
        ?int $stateId,
        ?int $cityId,
        string $countryAttribute,
        string $stateAttribute,
        string $cityAttribute
    ): array {
        $country = $countryId ? Country::query()->find($countryId) : null;
        $state = $stateId ? State::query()->find($stateId) : null;
        $city = $cityId ? City::query()->find($cityId) : null;
        $errors = [];

        if ($state && !$country) {
            $errors[$countryAttribute] = 'Selecciona un paÃ­s antes de seleccionar un estado.';
        }

        if ($state && $country && (int) $state->country_id !== (int) $country->id) {
            $errors[$stateAttribute] = 'El estado seleccionado no pertenece al paÃ­s indicado.';
        }

        if ($city && !$state) {
            $errors[$stateAttribute] = 'Selecciona un estado antes de seleccionar una ciudad.';
        }

        if ($city && $state && (int) $city->state_id !== (int) $state->id) {
            $errors[$cityAttribute] = 'La ciudad seleccionada no pertenece al estado indicado.';
        }

        if ($city && $country && (int) $city->country_id !== (int) $country->id) {
            $errors[$cityAttribute] = 'La ciudad seleccionada no pertenece al paÃ­s indicado.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return [$country, $state, $city];
    }

    protected function buildSourceMembershipPayload(Membership $membership): array
    {
        $sourceMember = $membership->account?->primaryHolder?->member;

        return [
            'id' => $membership->id,
            'membership_number' => $membership->account?->membership_number,
            'holder_name' => trim(collect([
                $sourceMember?->first_name,
                $sourceMember?->last_name,
                $sourceMember?->second_last_name,
            ])->filter()->implode(' ')),
            'membership_type_name' => $membership->membershipType?->name,
            'membership_type_code' => $membership->membershipType?->code,
            'club_name' => $membership->club?->name,
            'club_code' => $membership->club?->code,
            'status' => $membership->status,
            'start_date' => $membership->start_date,
        ];
    }

    protected function buildAccountMemberPayload(?MembershipAccountMember $accountMember): ?array
    {
        if (!$accountMember) {
            return null;
        }

        $member = $accountMember->member;

        return [
            'member_id' => $accountMember->member_id,
            'full_name' => trim(collect([
                $member?->first_name,
                $member?->last_name,
                $member?->second_last_name,
            ])->filter()->implode(' ')),
            'relationship_name' => $accountMember->relationship?->name,
            'email' => $member?->email,
            'phone' => $member?->phone,
            'is_primary_holder' => (bool) $accountMember->is_primary_holder,
        ];
    }

    protected function buildMembershipAccountPayload(Membership $membership): array
    {
        $activeMemberships = $membership->account->memberships
            ->where('status', 'active')
            ->where('is_primary', true)
            ->values();
        $billableMembership = $activeMemberships->firstWhere('is_billable', true);

        return [
            'id' => $membership->account?->id,
            'membership_number' => $membership->account?->membership_number,
            'account_type' => $membership->account?->account_type,
            'status' => $membership->account?->status,
            'current_monthly_fee' => (float) ($billableMembership?->monthly_fee ?? 0),
            'primary_holder' => $this->buildDetailedAccountMemberPayload($membership->account?->primaryHolder),
            'members' => $membership->account->accountMembers
                ->sortByDesc('is_primary_holder')
                ->map(fn(MembershipAccountMember $accountMember) => $this->buildDetailedAccountMemberPayload($accountMember))
                ->values(),
            'active_memberships' => $activeMemberships
                ->map(function (Membership $activeMembership) {
                    return [
                        'id' => $activeMembership->id,
                        'membership_type_name' => $activeMembership->membershipType?->name,
                        'membership_type_code' => $activeMembership->membershipType?->code,
                        'club_name' => $activeMembership->club?->name,
                        'club_code' => $activeMembership->club?->code,
                        'monthly_fee' => (float) $activeMembership->monthly_fee,
                        'is_billable' => (bool) $activeMembership->is_billable,
                        'status' => $activeMembership->status,
                        'start_date' => $activeMembership->start_date,
                        'end_date' => $activeMembership->end_date,
                    ];
                })
                ->values(),
        ];
    }

    protected function buildDetailedAccountMemberPayload(?MembershipAccountMember $accountMember): ?array
    {
        if (!$accountMember) {
            return null;
        }

        $member = $accountMember->member;
        $address = $member?->primaryAddress;
        $employment = $member?->employmentInfo;

        return [
            ...$this->buildAccountMemberPayload($accountMember),
            'relationship_id' => $accountMember->relationship_id,
            'birthdate' => $member?->birthdate,
            'age' => $member?->birthdate ? Carbon::parse($member->birthdate)->age : null,
            'birth_place' => $member?->birthCountry?->name ?? $member?->birth_place,
            'city' => $member?->birthCity?->name ?? $member?->city,
            'state' => $member?->birthState?->name ?? $member?->state,
            'nationality' => $member?->nationality?->demonym ?: $member?->nationality?->name,
            'marital_status' => $member?->maritalStatus?->name,
            'occupation' => $member?->occupation,
            'school_name' => $member?->school_name,
            'address' => [
                'street' => $address?->street,
                'neighborhood' => $address?->neighborhood,
                'postal_code' => $address?->postal_code,
                'city' => $address?->city?->name ?? $address?->city,
                'state' => $address?->state?->name ?? $address?->state,
                'country' => $address?->country?->name ?? $address?->country,
                'years_in_city' => $address?->years_in_city,
            ],
            'employment' => [
                'company_name' => $employment?->company_name,
                'company_address' => $employment?->company_address,
                'company_phone' => $employment?->company_phone,
            ],
        ];
    }

    protected function buildSeparationCandidateMembers(Membership $membership)
    {
        return $membership->account->accountMembers
            ->where('is_primary_holder', false)
            ->map(function (MembershipAccountMember $accountMember) use ($membership) {
                $memberPayload = $this->buildAccountMemberPayload($accountMember);
                $age = $accountMember->member?->birthdate
                    ? Carbon::parse($accountMember->member->birthdate)->age
                    : null;

                return [
                    ...$memberPayload,
                    'age' => $age,
                    'target_membership_options' => $this->buildSeparationTargetOptions($membership, $accountMember)
                        ->values(),
                ];
            })
            ->filter(fn(array $candidate) => !empty($candidate['target_membership_options']))
            ->values();
    }

    protected function buildSeparationTargetOptions(Membership $membership, MembershipAccountMember $accountMember)
    {
        $member = $accountMember->member;
        $fromMembershipType = $membership->membershipType;
        $age = $member?->birthdate ? Carbon::parse($member->birthdate)->age : null;

        if ($this->memberHasActivePrimaryMembershipInClub($accountMember->member_id, $membership->club_id)) {
            return collect();
        }

        $hasMultipleClubs = $this->memberHasOtherActiveClubMembership($accountMember->member_id, $membership->club_id);

        $targetMembershipTypes = MembershipType::query()
            ->where('club_id', $membership->club_id)
            ->where('allows_multiple_members', false)
            ->whereHas('pricingRules', function (Builder $query) use ($fromMembershipType) {
                $query->where('from_membership_type_id', $fromMembershipType->id);
            })
            ->orderBy('name')
            ->get();

        return $targetMembershipTypes
            ->map(function (MembershipType $targetMembershipType) use ($fromMembershipType, $membership, $age, $hasMultipleClubs) {
                try {
                    $pricing = $this->resolveApplicablePricing(
                        targetClubId: (int) $membership->club_id,
                        membershipType: $targetMembershipType,
                        fromMembershipType: $fromMembershipType,
                        sourceClub: $membership->club,
                        age: $age,
                        hasMultipleClubs: $hasMultipleClubs,
                        sourceMembershipIsActive: true,
                        yearsInSourceClub: $membership->start_date
                        ? Carbon::parse($membership->start_date)->diffInYears(now())
                        : null
                    );

                    return [
                        'id' => $targetMembershipType->id,
                        'code' => $targetMembershipType->code,
                        'name' => $targetMembershipType->name,
                        'monthly_fee' => (float) $pricing['monthly_fee'],
                        'inscription_fee' => (float) ($pricing['inscription_fee'] ?? 0),
                    ];
                } catch (ValidationException $e) {
                    return null;
                }
            })
            ->filter()
            ->values();
    }

    protected function memberHasOtherActiveClubMembership(int $memberId, int $currentClubId): bool
    {
        return Membership::query()
            ->where('status', 'active')
            ->where('club_id', '!=', $currentClubId)
            ->whereHas('account.accountMembers', function (Builder $query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->exists();
    }

    protected function memberHasActivePrimaryMembershipInClub(int $memberId, int $clubId): bool
    {
        return Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', $clubId)
            ->whereHas('account.primaryHolder', function (Builder $query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->exists();
    }

    protected function resolveAge(array $memberData): ?int
    {
        if (!empty($memberData['age'])) {
            return (int) $memberData['age'];
        }

        if (empty($memberData['birthdate'])) {
            return null;
        }

        return Carbon::parse($memberData['birthdate'])->age;
    }

    protected function resolveApplicablePricing(
        int $targetClubId,
        MembershipType $membershipType,
        ?MembershipType $fromMembershipType,
        ?Club $sourceClub,
        ?int $age,
        bool $hasMultipleClubs,
        bool $sourceMembershipIsActive,
        ?int $yearsInSourceClub
    ): array {
        $interclubRule = $this->resolveInterclubPackageRule(
            targetClubId: $targetClubId,
            membershipType: $membershipType,
            fromMembershipType: $fromMembershipType,
            sourceClub: $sourceClub,
            sourceMembershipIsActive: $sourceMembershipIsActive,
            yearsInSourceClub: $yearsInSourceClub
        );

        if ($interclubRule) {
            return [
                'monthly_fee' => (float) $interclubRule->monthly_fee,
                'inscription_fee' => (float) $interclubRule->inscription_fee,
                'rule_type' => 'interclub',
                'source_membership_becomes_non_billable' => true,
            ];
        }

        $this->ensurePe1PackageEligibility(
            membershipType: $membershipType,
            sourceClub: $sourceClub,
            yearsInSourceClub: $yearsInSourceClub
        );

        $pricingRule = $this->resolvePricingRule(
            membershipTypeId: $membershipType->id,
            fromMembershipTypeId: $fromMembershipType?->id,
            age: $this->shouldApplyAgeFilter($membershipType) ? $age : null,
            hasMultipleClubs: $hasMultipleClubs
        );

        if (!$pricingRule) {
            throw ValidationException::withMessages([
                'membership_type_id' => 'No se encontró una regla de costo aplicable para la membresía seleccionada.',
            ]);
        }

        return [
            'monthly_fee' => (float) $pricingRule->monthly_fee,
            'inscription_fee' => (float) ($pricingRule->inscription_fee ?? 0),
            'rule_type' => 'pricing_rule',
            'source_membership_becomes_non_billable' => $this->shouldSourceMembershipBecomeNonBillable(
                membershipType: $membershipType,
                fromMembershipType: $fromMembershipType,
                pricingRule: $pricingRule
            ),
        ];
    }

    protected function resolveInterclubPackageRule(
        int $targetClubId,
        MembershipType $membershipType,
        ?MembershipType $fromMembershipType,
        ?Club $sourceClub,
        bool $sourceMembershipIsActive,
        ?int $yearsInSourceClub
    ): ?InterclubPackageRule {
        if (!$sourceClub || !$fromMembershipType) {
            return null;
        }

        return InterclubPackageRule::query()
            ->where('target_club_id', $targetClubId)
            ->where('target_membership_type_id', $membershipType->id)
            ->where('source_club_id', $sourceClub->id)
            ->where(function (Builder $query) use ($fromMembershipType) {
                $query->where('source_membership_type_id', $fromMembershipType->id)
                    ->orWhereNull('source_membership_type_id');
            })
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now()->toDateString());
            })
            ->where(function (Builder $query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now()->toDateString());
            })
            ->when(
                !$sourceMembershipIsActive,
                fn(Builder $query) => $query->where('requires_active_source_membership', false)
            )
            ->when(
                $yearsInSourceClub !== null,
                fn(Builder $query) => $query->where(function (Builder $yearsQuery) use ($yearsInSourceClub) {
                    $yearsQuery->whereNull('min_years_in_source_club')
                        ->orWhere('min_years_in_source_club', '<=', $yearsInSourceClub);
                }),
                fn(Builder $query) => $query->whereNull('min_years_in_source_club')
            )
            ->orderByRaw('CASE WHEN source_membership_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('priority')
            ->first();
    }

    protected function ensurePe1PackageEligibility(
        MembershipType $membershipType,
        ?Club $sourceClub,
        ?int $yearsInSourceClub
    ): void {
        if (!$this->isPe1PackageMembershipType($membershipType)) {
            return;
        }

        if (!$sourceClub || $sourceClub->code !== 'PE1') {
            throw ValidationException::withMessages([
                'source_club_id' => 'El paquete Parque España 1 solo aplica para socios provenientes de PE1.',
            ]);
        }

        if ($yearsInSourceClub === null || $yearsInSourceClub < 5) {
            throw ValidationException::withMessages([
                'years_in_source_club' => 'El paquete Parque España 1 requiere al menos 5 años de antigüedad en PE1.',
            ]);
        }
    }

    protected function isPe1PackageMembershipType(MembershipType $membershipType): bool
    {
        return Str::endsWith((string) $membershipType->code, '_PE1');
    }

    protected function isMonthlyPassMembershipType(?MembershipType $membershipType): bool
    {
        return $membershipType !== null
            && Str::startsWith((string) $membershipType->code, 'PE2_PM_');
    }

    protected function shouldSourceMembershipBecomeNonBillable(
        MembershipType $membershipType,
        ?MembershipType $fromMembershipType,
        PricingRule $pricingRule
    ): bool {
        if ($this->isPe1PackageMembershipType($membershipType)) {
            return true;
        }

        if (!$pricingRule->requires_multiple_clubs) {
            return false;
        }

        if ($this->isMonthlyPassMembershipType($fromMembershipType)) {
            return false;
        }

        return true;
    }

    protected function shouldApplyAgeFilter(MembershipType $membershipType): bool
    {
        return Str::contains((string) $membershipType->code, '_SOL');
    }

    protected function resolvePricingRule(
        int $membershipTypeId,
        ?int $fromMembershipTypeId,
        ?int $age,
        bool $hasMultipleClubs
    ): ?PricingRule {
        $attempts = [];

        if ($fromMembershipTypeId) {
            if ($hasMultipleClubs) {
                $attempts[] = [$fromMembershipTypeId, true];
            }

            $attempts[] = [$fromMembershipTypeId, false];
        }

        if ($hasMultipleClubs) {
            $attempts[] = [null, true];
        }

        $attempts[] = [null, false];

        foreach ($attempts as [$candidateFromMembershipTypeId, $requiresMultipleClubs]) {
            $rule = $this->buildPricingRuleQuery(
                membershipTypeId: $membershipTypeId,
                fromMembershipTypeId: $candidateFromMembershipTypeId,
                age: $age,
                requiresMultipleClubs: $requiresMultipleClubs
            )
                ->orderBy('priority')
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        return null;
    }

    protected function buildPricingRuleQuery(
        int $membershipTypeId,
        ?int $fromMembershipTypeId,
        ?int $age,
        bool $requiresMultipleClubs
    ): Builder {
        return PricingRule::query()
            ->where('membership_type_id', $membershipTypeId)
            ->when(
                $fromMembershipTypeId !== null,
                fn(Builder $query) => $query->where('from_membership_type_id', $fromMembershipTypeId),
                fn(Builder $query) => $query->whereNull('from_membership_type_id')
            )
            ->when(
                $age !== null,
                function (Builder $query) use ($age) {
                    $query->where(function (Builder $ageQuery) use ($age) {
                        $ageQuery->whereNull('min_age')->orWhere('min_age', '<=', $age);
                    })->where(function (Builder $ageQuery) use ($age) {
                        $ageQuery->whereNull('max_age')->orWhere('max_age', '>=', $age);
                    });
                },
                fn(Builder $query) => $query->whereNull('min_age')->whereNull('max_age')
            )
            ->where('requires_multiple_clubs', $requiresMultipleClubs);
    }

    protected function generateMembershipNumber(Club $club): string
    {
        return sprintf(
            '%s-%s',
            Str::upper($club->code ?: 'MEM'),
            now()->format('YmdHisv')
        );
    }

    protected function hasFilledValues(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    protected function membershipAccountHasSpouse(Membership $membership): bool
    {
        return $membership->account->accountMembers
            ->contains(fn(MembershipAccountMember $accountMember) => $this->isSpouseRelationship($accountMember->relationship?->name));
    }

    protected function resolveAdditionalMonthlyCharge(
        ?float $currentMonthlyFee,
        float $newMonthlyFee,
        bool $sourceMembershipBecomesNonBillable
    ): ?float {
        if ($currentMonthlyFee === null) {
            return null;
        }

        if (!$sourceMembershipBecomesNonBillable) {
            return null;
        }

        return round($newMonthlyFee - $currentMonthlyFee, 2);
    }

    protected function buildPricingPreviewExplanation(
        ?float $currentMonthlyFee,
        float $newMonthlyFee,
        float $inscriptionFee,
        bool $sourceMembershipBecomesNonBillable,
        bool $sameClubTransition
    ): string {
        $formattedNewMonthlyFee = number_format($newMonthlyFee, 2);
        $formattedInscriptionFee = number_format($inscriptionFee, 2);

        if ($sameClubTransition) {
            if ($inscriptionFee > 0) {
                return "Se actualizará la cuota mensual a $$formattedNewMonthlyFee y se cobrará un cargo extra de inscripción por $$formattedInscriptionFee.";
            }

            return "Se actualizará la cuota mensual a $$formattedNewMonthlyFee.";
        }

        if ($sourceMembershipBecomesNonBillable && $currentMonthlyFee !== null) {
            $additionalCharge = round($newMonthlyFee - $currentMonthlyFee, 2);
            $formattedCurrentMonthlyFee = number_format($currentMonthlyFee, 2);
            $formattedAdditionalCharge = number_format(abs($additionalCharge), 2);

            if ($additionalCharge > 0) {
                $message = "La nueva mensualidad total será de $$formattedNewMonthlyFee. Como actualmente se pagan $$formattedCurrentMonthlyFee, el ajuste adicional mensual será de $$formattedAdditionalCharge.";
            } elseif ($additionalCharge === 0.0) {
                $message = "La nueva mensualidad total se mantiene en $$formattedNewMonthlyFee, por lo que no habrá ajuste adicional mensual.";
            } else {
                $message = "La nueva mensualidad total será de $$formattedNewMonthlyFee, lo que representa una disminución de $$formattedAdditionalCharge respecto a la cuota actual de $$formattedCurrentMonthlyFee.";
            }

            if ($inscriptionFee > 0) {
                $message .= " Además, se cobrará una inscripción de $$formattedInscriptionFee.";
            }

            return $message;
        }

        if ($inscriptionFee > 0) {
            return "Se cobrará una mensualidad de $$formattedNewMonthlyFee y una inscripción de $$formattedInscriptionFee.";
        }

        return "Se cobrará una mensualidad de $$formattedNewMonthlyFee.";
    }

    protected function isTitularRelationship(?string $relationshipName): bool
    {
        return $this->normalizeRelationshipName($relationshipName) === 'titular';
    }

    protected function isChildRelationship(?string $relationshipName): bool
    {
        return in_array($this->normalizeRelationshipName($relationshipName), ['hijo(a)', 'hijo', 'hija'], true);
    }

    protected function isSpouseRelationship(?string $relationshipName): bool
    {
        return in_array($this->normalizeRelationshipName($relationshipName), ['conyuge', 'esposo', 'esposa'], true);
    }

    protected function normalizeRelationshipName(?string $relationshipName): string
    {
        return Str::lower(trim(Str::ascii($relationshipName ?? '')));
    }

    protected function createAccountGroup(): MembershipAccountGroup
    {
        return MembershipAccountGroup::create([
            'status' => 'active',
        ]);
    }

    protected function createMembershipAccount(
        Club $club,
        string $accountType,
        string $status = 'pending',
        ?MembershipAccountGroup $accountGroup = null
    ): MembershipAccount {
        $group = $accountGroup ?? $this->createAccountGroup();

        return MembershipAccount::create([
            'account_group_id' => $group->id,
            'membership_number' => $this->generateMembershipNumber($club),
            'account_type' => $accountType,
            'status' => $status,
        ]);
    }

    protected function validationExceptionResponse(ValidationException $e)
    {
        $errors = $e->errors();
        $firstMessage = collect($errors)->flatten()->first() ?? 'Ocurrió un error de validación.';

        return redirect()->back()->withErrors(array_merge($errors, [
            'messageError' => $firstMessage,
            'exception' => '',
        ]));
    }
}
