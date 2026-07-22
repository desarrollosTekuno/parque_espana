<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Catalogs\City;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\MaritalStatus;
use App\Models\Catalogs\Relationship;
use App\Models\Catalogs\State;
use App\Models\Members\Address;
use App\Models\Members\EmploymentInfo;
use App\Models\Members\Member;
use App\Models\Members\MemberDocument;
use App\Models\Memberships\AbsencePermit;
use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\Membership;
use App\Models\Members\LockerAssignment;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Models\Memberships\SeparationReason;
use App\Services\Billing\MembershipChargeService;
use App\Rules\ExistsInSchema;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Rules\UniqueInSchema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;

class MemberController extends Controller
{
    public function __construct(
        protected MembershipChargeService $membershipChargeService,
        protected \App\Services\Billing\MembershipPricingService $membershipPricingService
    ) {
    }

    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $prefix = 'members';
            $driver = DB::getDriverName();
            $like = $driver === 'pgsql' ? 'ilike' : 'like';

            $query = MembershipAccount::query()
                ->with([
                    'club',
                    'primaryHolder.member',
                    'memberships' => fn($membershipQuery) => $membershipQuery
                        ->with(['membershipType', 'club'])
                        ->where('status', 'active')
                        ->where('is_primary', true),
                ])
                ->withCount('accountMembers')
                ->join(
                    'memberships.memberships as _m_filter',
                    fn ($join) => $join
                        ->on('_m_filter.membership_account_id', '=', 'memberships.accounts.id')
                        ->where('_m_filter.club_id', $clubId)
                        ->where('_m_filter.status', 'active')
                        ->where('_m_filter.is_primary', true)
                )
                ->join(
                    'memberships.account_members as _am_filter',
                    fn ($join) => $join
                        ->on('_am_filter.membership_account_id', '=', 'memberships.accounts.id')
                        ->where('_am_filter.is_primary_holder', true)
                )
                ->join('members.members as _holder', '_holder.id', '=', '_am_filter.member_id')
                ->select('memberships.accounts.*');

            if ($search = $request->input("{$prefix}_search")) {
                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('memberships.accounts.membership_number', $like, "%{$search}%")
                        ->orWhere('memberships.accounts.internal_account_number', $like, "%{$search}%")
                        ->orWhere('_holder.first_name', $like, "%{$search}%")
                        ->orWhere('_holder.last_name', $like, "%{$search}%")
                        ->orWhere('_holder.second_last_name', $like, "%{$search}%")
                        ->orWhere('_holder.email', $like, "%{$search}%")
                        ->orWhere('_holder.phone', $like, "%{$search}%")
                        ->orWhereHas('memberships.membershipType', function (Builder $q) use ($search, $like) {
                            $q->where('name', $like, "%{$search}%");
                        });
                });
            }

            $sortMap = [
                'id' => 'memberships.accounts.id',
                'membership_number' => 'memberships.accounts.membership_number',
                'created_at' => 'memberships.accounts.created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'id');
            $order = $request->input("{$prefix}_order", 'desc');
            $sortColumn = $sortMap[$sort] ?? 'memberships.accounts.id';

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
                        'internal_account_number' => $account->internal_account_number,
                        'account_club_name' => $account->club?->name ?? $currentMembership?->club?->name,
                        'account_club_code' => $account->club?->code ?? $currentMembership?->club?->code,
                        'holder_name' => $fullName,
                        'email' => $holder?->email,
                        'phone' => $holder?->phone,
                        'monthly_fee' => (float) $activeMemberships->sum(fn (Membership $membership) => $membership->resolved_monthly_fee_share),
                        'status' => $currentMembership?->status,
                        'can_change_membership' => $currentMembership !== null
                            && Str::contains($currentMembershipCode, '_IND'),
                        'can_change_primary_holder' => (bool) ($currentMembership?->membershipType?->allows_multiple_members)
                            && (int) $account->account_members_count > 1,
                        'can_separate_member' => (bool) ($currentMembership?->membershipType?->allows_multiple_members)
                            && (int) $account->account_members_count > 1,
                        'can_cancel_membership' => Gate::allows('members.cancel.create'),
                        'can_create_membership' => Gate::allows('members.additional-membership.create'),
                        'active_memberships' => $activeMemberships->map(function (Membership $membership) {
                            return [
                                'id' => $membership->id,
                                'membership_type_name' => $membership->membershipType?->name,
                                'membership_type_code' => $membership->membershipType?->code,
                                'club_name' => $membership->club?->name,
                                'club_code' => $membership->club?->code,
                                'monthly_fee' => (float) $membership->resolved_monthly_fee_share,
                                'monthly_fee_total' => (float) $membership->resolved_monthly_fee_total,
                                'monthly_fee_share' => (float) $membership->resolved_monthly_fee_share,
                                'billing_split_mode' => $membership->billing_split_mode,
                                'is_billable' => (bool) $membership->is_billable,
                                'start_date' => $membership->start_date,
                                'end_date' => $membership->end_date,
                                'status' => $membership->status,
                            ];
                        })->values(),
                    ];
                })
                ->appends($request->all());

            $cancelledAccounts = ['data' => [], 'total' => 0];

            if (auth()->user()?->can('members.reactivate')) {
                $cancelledPrefix = 'cancelled';
                $cancelledSearch = $request->input("{$cancelledPrefix}_search");
                $cancelledSort = $request->input("{$cancelledPrefix}_sort", 'id');
                $cancelledOrder = $request->input("{$cancelledPrefix}_order", 'desc');
                $cancelledSortColumn = $sortMap[$cancelledSort] ?? 'memberships.accounts.id';

                $cancelledQuery = MembershipAccount::query()
                    ->with([
                        'club',
                        'primaryHolder.member',
                        'memberships' => fn ($q) => $q
                            ->with(['membershipType', 'club'])
                            ->where('is_primary', true),
                    ])
                    ->withCount('accountMembers')
                    ->where('memberships.accounts.status', 'cancelled')
                    ->where('memberships.accounts.cancellation_type', 'voluntary')
                    ->join(
                        'memberships.memberships as _cm_filter',
                        fn ($join) => $join
                            ->on('_cm_filter.membership_account_id', '=', 'memberships.accounts.id')
                            ->where('_cm_filter.club_id', $clubId)
                            ->where('_cm_filter.is_primary', true)
                    )
                    ->join(
                        'memberships.account_members as _cam_filter',
                        fn ($join) => $join
                            ->on('_cam_filter.membership_account_id', '=', 'memberships.accounts.id')
                            ->where('_cam_filter.is_primary_holder', true)
                    )
                    ->join('members.members as _cholder', '_cholder.id', '=', '_cam_filter.member_id')
                    ->select('memberships.accounts.*');

                if ($cancelledSearch) {
                    $cancelledQuery->where(function (Builder $b) use ($cancelledSearch, $like) {
                        $b->where('memberships.accounts.membership_number', $like, "%{$cancelledSearch}%")
                            ->orWhere('_cholder.first_name', $like, "%{$cancelledSearch}%")
                            ->orWhere('_cholder.last_name', $like, "%{$cancelledSearch}%")
                            ->orWhere('_cholder.second_last_name', $like, "%{$cancelledSearch}%")
                            ->orWhere('_cholder.email', $like, "%{$cancelledSearch}%");
                    });
                }

                $cancelledAccounts = $cancelledQuery
                    ->orderBy($cancelledSortColumn, $cancelledOrder)
                    ->paginate(
                        $request->input("{$cancelledPrefix}_per_page", 10),
                        ['*'],
                        "{$cancelledPrefix}_page",
                        $request->input("{$cancelledPrefix}_page", 1)
                    )
                    ->through(function (MembershipAccount $account) use ($clubId) {
                        $holder = $account->primaryHolder?->member;
                        $primaryMembership = $account->memberships
                            ->where('club_id', (int) $clubId)
                            ->where('is_primary', true)
                            ->first();

                        return [
                            'id' => $account->id,
                            'membership_id' => $primaryMembership?->id,
                            'membership_number' => $account->membership_number,
                            'holder_name' => trim(collect([
                                $holder?->first_name,
                                $holder?->last_name,
                                $holder?->second_last_name,
                            ])->filter()->implode(' ')),
                            'email' => $holder?->email,
                            'membership_type_name' => $primaryMembership?->membershipType?->name,
                            'cancelled_at' => $account->cancelled_at,
                            'members_count' => (int) $account->account_members_count,
                        ];
                    })
                    ->appends($request->all());
            }

            return Inertia::render('Members/Index', [
                'members' => $members,
                'pendingMembersCount' => $pendingMembersCount,
                'cancelledAccounts' => $cancelledAccounts,
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('Members/Index', [
                'members' => [
                    'data' => [],
                    'total' => 0,
                ],
                'pendingMembersCount' => 0,
                'cancelledAccounts' => [
                    'data' => [],
                    'total' => 0,
                ],
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
                'documentTypes:id,name,allowed_extensions,min_age,max_age,max_file_size_kb',
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
                    'message' => 'No hay un club seleccionado en la sesión.',
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
                $currentMonthlyFee = $this->resolveCurrentGroupMonthlyFee($sourceMembership);

                if (!$sameClubTransition) {
                    $hasMultipleClubs = true;
                }

                $sourcePrimaryHolderId = $sourceMembership->account?->primaryHolder?->member_id;

                if (
                    $sourcePrimaryHolderId
                    && $this->memberHasOtherActiveClubMembership((int) $sourcePrimaryHolderId, (int) $clubId)
                ) {
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

            if ($this->membershipPricingService->shouldApplyAgeFilter($membershipType) && $age === null) {
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

            $newMonthlyFeeTotal = (float) $pricing['monthly_fee'];
            $billingSplitMode = (string) ($pricing['billing_split_mode'] ?? 'single');
            $usesSharedBilling = $billingSplitMode === 'equal_split';
            $newMonthlyFeeShare = $this->resolvePreviewMonthlyFeeShare(
                $newMonthlyFeeTotal,
                $billingSplitMode
            );
            $inscriptionFee = (float) ($pricing['inscription_fee'] ?? 0);
            $additionalMonthlyCharge = $this->resolveAdditionalMonthlyCharge(
                currentMonthlyFee: $currentMonthlyFee,
                newMonthlyFeeTotal: $newMonthlyFeeTotal,
                usesSharedBilling: $usesSharedBilling
            );
            $amountDueToday = $this->resolvePreviewAmountDueToday(
                currentMonthlyFee: $currentMonthlyFee,
                newMonthlyFeeTotal: $newMonthlyFeeTotal,
                newMonthlyFeeShare: $newMonthlyFeeShare,
                inscriptionFee: $inscriptionFee,
                usesSharedBilling: $usesSharedBilling
            );

            return response()->json([
                'membership_type_id' => $membershipType->id,
                'membership_type_name' => $membershipType->name,
                'membership_type_code' => $membershipType->code,
                'monthly_fee' => $newMonthlyFeeShare,
                'monthly_fee_total' => $newMonthlyFeeTotal,
                'monthly_fee_share' => $newMonthlyFeeShare,
                'inscription_fee' => $inscriptionFee,
                'total_due' => $amountDueToday,
                'amount_due_today' => $amountDueToday,
                'rule_type' => $pricing['rule_type'] ?? null,
                'billing_split_mode' => $billingSplitMode,
                'source_membership_becomes_non_billable' => (bool) ($pricing['source_membership_becomes_non_billable'] ?? false),
                'current_monthly_fee' => $currentMonthlyFee,
                'additional_monthly_charge' => $additionalMonthlyCharge,
                'charge_explanation' => $this->buildPricingPreviewExplanation(
                    currentMonthlyFee: $currentMonthlyFee,
                    newMonthlyFeeTotal: $newMonthlyFeeTotal,
                    newMonthlyFeeShare: $newMonthlyFeeShare,
                    inscriptionFee: $inscriptionFee,
                    amountDueToday: $amountDueToday,
                    additionalMonthlyCharge: $additionalMonthlyCharge,
                    sameClubTransition: $sameClubTransition,
                    usesSharedBilling: $usesSharedBilling
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

        $account = $membership->account;
        $account->loadMissing([
            'originAccount.primaryHolder.member',
            'originAccount.memberships' => fn ($q) => $q->where('is_primary', true),
            'derivedAccounts.primaryHolder.member',
            'derivedAccounts.memberships' => fn ($q) => $q->where('is_primary', true),
        ]);

        $accountTree = $this->buildAccountTree($account);

        return Inertia::render('Members/Show', [
            'membership'       => $this->buildSourceMembershipPayload($membership),
            'account'          => $this->buildMembershipAccountPayload($membership),
            'accountTree'      => $accountTree,
            'canAddFamilyMembers' => (bool) $membership->membershipType?->allows_multiple_members,
            'canChangePrimaryHolder' => (bool) $membership->membershipType?->allows_multiple_members
                && $account->accountMembers->count() > 1,
            'canSeparateMembers' => (bool) $membership->membershipType?->allows_multiple_members
                && $account->accountMembers->where('is_primary_holder', false)->isNotEmpty(),
        ]);
    }

    public function membershipHistory(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $perPage = min((int) $request->input('per_page', 10), 50);
        $page    = max((int) $request->input('page', 1), 1);

        $membership->loadMissing('account.memberships');
        $accountMembershipIds = $membership->account->memberships->pluck('id');

        $query = DB::table('memberships.membership_history as mh')
            ->leftJoin('memberships.types as old_type', 'mh.old_membership_type_id', '=', 'old_type.id')
            ->join('memberships.types as new_type', 'mh.new_membership_type_id', '=', 'new_type.id')
            ->leftJoin('users as u', 'mh.changed_by', '=', 'u.id')
            ->whereIn('mh.membership_id', $accountMembershipIds)
            ->orderByDesc('mh.effective_date')
            ->orderByDesc('mh.created_at')
            ->select([
                'mh.id',
                'mh.effective_date',
                'mh.reason',
                'mh.previous_monthly_fee',
                'mh.new_monthly_fee',
                'old_type.name as old_membership_type_name',
                'new_type.name as new_membership_type_name',
                'u.name as changed_by_name',
            ]);

        $total = $query->count();
        $rows  = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $items = $rows->map(fn ($row) => [
            'id'                       => $row->id,
            'effective_date'           => $row->effective_date,
            'reason'                   => $row->reason,
            'previous_monthly_fee'     => $row->previous_monthly_fee !== null ? (float) $row->previous_monthly_fee : null,
            'new_monthly_fee'          => $row->new_monthly_fee !== null ? (float) $row->new_monthly_fee : null,
            'old_membership_type_name' => $row->old_membership_type_name,
            'new_membership_type_name' => $row->new_membership_type_name,
            'changed_by_name'          => $row->changed_by_name,
        ]);

        return response()->json([
            'data'  => $items,
            'total' => $total,
        ]);
    }

    public function storeAbsencePermit(Request $request, Membership $membership)
    {
        try {
            $clubId = session('club_id');

            if ((int) $membership->club_id !== (int) $clubId) {
                abort(404);
            }

            $membership = $this->loadMembershipContext($membership);

            if ($membership->status !== 'active' || !$membership->is_primary) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Solo puedes registrar un permiso por ausencia para una membresía activa y principal.',
                    'exception' => '',
                ]);
            }

            $validated = $request->validate([
                'start_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
                'end_month'   => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
                'charge_percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'absence_permit_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ], [
                'absence_permit_document.required' => 'El documento de solicitud de permiso por ausencia es obligatorio.',
                'absence_permit_document.mimes' => 'El documento debe ser un archivo PDF, JPG o PNG.',
                'absence_permit_document.max' => 'El documento no debe superar los 5 MB.',
            ]);

            $startDate = Carbon::createFromFormat('Y-m', $validated['start_month'])->startOfMonth()->startOfDay();
            $endDate   = Carbon::createFromFormat('Y-m', $validated['end_month'])->endOfMonth()->startOfDay();

            $currentMonthStart = now()->startOfMonth()->startOfDay();

            if ($startDate->lt($currentMonthStart)) {
                throw ValidationException::withMessages([
                    'start_month' => 'El mes de inicio debe ser el mes actual o uno futuro.',
                ]);
            }

            if ($endDate->lt($startDate)) {
                throw ValidationException::withMessages([
                    'end_month' => 'El mes de fin debe ser igual o posterior al mes de inicio.',
                ]);
            }

            $accountGroup = $membership->account?->accountGroup;
            $primaryHolder = $membership->account?->primaryHolder;

            if (!$accountGroup || !$primaryHolder?->member_id) {
                return redirect()->back()->withErrors([
                    'messageError' => 'La cuenta no tiene un grupo o titular válido para registrar el permiso por ausencia.',
                    'exception' => '',
                ]);
            }

            $overlappingPermit = AbsencePermit::query()
                ->where('account_group_id', $accountGroup->id)
                ->whereIn('status', ['approved', 'active'])
                ->whereDate('start_date', '<=', $endDate->toDateString())
                ->whereDate('end_date', '>=', $startDate->toDateString())
                ->exists();

            if ($overlappingPermit) {
                throw ValidationException::withMessages([
                    'start_date' => 'Ya existe un permiso por ausencia vigente o programado que se cruza con el período seleccionado.',
                ]);
            }

            DB::transaction(function () use ($request, $membership, $accountGroup, $primaryHolder, $startDate, $endDate, $validated) {
                AbsencePermit::create([
                    'account_group_id' => $accountGroup->id,
                    'membership_account_id' => $membership->membership_account_id,
                    'primary_member_id' => $primaryHolder->member_id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'charge_percentage' => (float) ($validated['charge_percentage'] ?? 25),
                    'status' => $this->resolveAbsencePermitStatus($startDate, $endDate),
                    'blocks_facility_access' => true,
                    'blocks_reservations' => true,
                    'notes' => $validated['notes'] ?? null,
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ]);

                $file = $request->file('absence_permit_document');
                $docType = \App\Models\Catalogs\DocumentType::where('code', 'documento_permiso_ausencia')->first();
                $docTypeSlug = $docType ? Str::slug($docType->name) : 'documento-permiso-ausencia';
                $directory = "members/{$primaryHolder->member_id}/{$docTypeSlug}";
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

                $uploaded = Storage::disk('spaces')->putFileAs($directory, $file, $filename);

                if ($uploaded === false) {
                    throw new \RuntimeException('No se pudo subir el documento del permiso por ausencia.');
                }

                MemberDocument::create([
                    'member_id'        => $primaryHolder->member_id,
                    'document_type_id' => $docType?->id,
                    'file_path'        => "{$directory}/{$filename}",
                ]);
            });

            return redirect()
                ->route('members.manage.show', $membership)
                ->with('success', 'Permiso por ausencia registrado correctamente.');
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al registrar el permiso por ausencia.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function cancelAbsencePermit(Request $request, Membership $membership, AbsencePermit $absencePermit)
    {
        try {
            $clubId = session('club_id');

            if ((int) $membership->club_id !== (int) $clubId) {
                abort(404);
            }

            $membership = $this->loadMembershipContext($membership);
            $accountGroupId = (int) ($membership->account?->account_group_id ?? 0);

            if ((int) $absencePermit->account_group_id !== $accountGroupId) {
                abort(404);
            }

            if (in_array($absencePermit->status, ['cancelled', 'finished'], true)) {
                return redirect()->back()->withErrors([
                    'messageError' => 'El permiso por ausencia ya no puede cancelarse.',
                    'exception' => '',
                ]);
            }

            $absencePermit->update([
                'status' => 'cancelled',
            ]);

            return redirect()
                ->route('members.manage.show', $membership)
                ->with('success', 'Permiso por ausencia cancelado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al cancelar el permiso por ausencia.',
                'exception' => $e->getMessage(),
            ]);
        }
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

        $currentAccountId = $membership->membership_account_id;
        $accountGroupId = $membership->account?->account_group_id;
        $currentMemberIds = $membership->account->accountMembers->pluck('member_id');

        $availableGroupMembers = collect();
        if ($accountGroupId) {
            $availableGroupMembers = MembershipAccountMember::query()
                ->with(['member', 'relationship', 'membershipAccount.club'])
                ->whereHas('membershipAccount', function (Builder $q) use ($accountGroupId, $currentAccountId) {
                    $q->where('account_group_id', $accountGroupId)
                      ->where('id', '!=', $currentAccountId);
                })
                ->whereNotIn('member_id', $currentMemberIds)
                ->where('is_primary_holder', false)
                ->get()
                ->unique('member_id')
                ->map(fn(MembershipAccountMember $am) => [
                    'member_id'         => $am->member_id,
                    'full_name'         => trim(implode(' ', array_filter([
                        $am->member?->first_name,
                        $am->member?->last_name,
                        $am->member?->second_last_name,
                    ]))),
                    'birthdate'         => $am->member?->birthdate,
                    'age'               => $am->member?->birthdate
                                            ? Carbon::parse($am->member->birthdate)->age
                                            : null,
                    'relationship_name' => $am->relationship?->name,
                    'club_name'         => $am->membershipAccount?->club?->name,
                    'club_code'         => $am->membershipAccount?->club?->code,
                ])
                ->values();
        }

        $membershipDocumentTypes = $membership->membershipType?->documentTypes
            ->map(fn ($dt) => [
                'id'                 => $dt->id,
                'name'               => $dt->name,
                'allowed_extensions' => $dt->allowed_extensions,
                'min_age'            => $dt->min_age !== null ? (int) $dt->min_age : null,
                'max_age'            => $dt->max_age !== null ? (int) $dt->max_age : null,
                'max_file_size_kb'   => $dt->max_file_size_kb !== null ? (int) $dt->max_file_size_kb : null,
                'pivot' => [
                    'is_required'    => (bool) $dt->pivot->is_required,
                    'allow_multiple' => (bool) $dt->pivot->allow_multiple,
                    'number_files'   => (int) $dt->pivot->number_files,
                ],
                'relationships' => $dt->relationships->map(fn ($r) => [
                    'id'   => $r->id,
                    'name' => $r->name,
                ])->values(),
            ])->values() ?? collect([]);

        return Inertia::render('Members/AddFamilyMember', [
            'membership' => $this->buildSourceMembershipPayload($membership),
            'account' => $this->buildMembershipAccountPayload($membership),
            ...$this->getCreateFormCatalogs(),
            'relationships' => Relationship::query()
                ->select('id', 'name')
                ->get()
                ->reject(fn(Relationship $relationship) => $this->isTitularRelationship($relationship->name))
                ->values(),
            'availableGroupMembers'    => $availableGroupMembers,
            'membershipDocumentTypes'  => $membershipDocumentTypes,
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

            // ── Caso A: vincular un miembro existente del grupo ────────────────
            if ($request->filled('existing_member_id')) {
                $validated = $request->validate([
                    'existing_member_id' => ['required', 'integer'],
                    'relationship_id'    => ['required', new ExistsInSchema('catalogs', 'relationships', 'id')],
                ]);

                $existingMember = Member::findOrFail($validated['existing_member_id']);
                $relationship   = Relationship::findOrFail($validated['relationship_id']);

                // Verificar que pertenezca al mismo grupo y no esté ya en la cuenta
                $accountGroupId    = $membership->account?->account_group_id;
                $currentAccountId  = $membership->membership_account_id;
                $currentMemberIds  = $membership->account->accountMembers->pluck('member_id');

                $isInGroup = MembershipAccountMember::query()
                    ->whereHas('membershipAccount', fn(Builder $q) => $q->where('account_group_id', $accountGroupId)
                        ->where('id', '!=', $currentAccountId))
                    ->where('member_id', $existingMember->id)
                    ->exists();

                if (!$isInGroup) {
                    throw ValidationException::withMessages([
                        'existing_member_id' => 'El integrante seleccionado no pertenece al grupo familiar.',
                    ]);
                }

                if ($currentMemberIds->contains($existingMember->id)) {
                    throw ValidationException::withMessages([
                        'existing_member_id' => 'El integrante ya forma parte de esta cuenta.',
                    ]);
                }

                if ($this->isTitularRelationship($relationship->name)) {
                    throw ValidationException::withMessages([
                        'relationship_id' => 'No puedes asignar el parentesco de titular.',
                    ]);
                }

                if ($this->isSpouseRelationship($relationship->name) && $this->membershipAccountHasSpouse($membership)) {
                    throw ValidationException::withMessages([
                        'relationship_id' => 'La cuenta familiar ya cuenta con un cónyuge registrado.',
                    ]);
                }

                MembershipAccountMember::create([
                    'membership_account_id' => $currentAccountId,
                    'member_id'             => $existingMember->id,
                    'relationship_id'       => $relationship->id,
                    'is_primary_holder'     => false,
                ]);

                return redirect()
                    ->route('members.manage.show', $membership)
                    ->with('success', 'El familiar se agregó correctamente a la cuenta.');
            }

            // ── Caso B: crear un nuevo integrante ───────────────────────────────
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
                'address.years_in_city' => ['nullable', 'integer', 'min:0', 'max:999'],
                'employment' => ['nullable', 'array'],
                'employment.company_name' => ['nullable', 'string', 'max:255'],
                'employment.company_address' => ['nullable', 'string', 'max:255'],
                'employment.company_phone' => ['nullable', 'string', 'max:50'],
                'documents' => ['nullable', 'array'],
                'documents.*.document_type_id' => ['required_with:documents.*', 'integer'],
                'documents.*.files' => ['nullable', 'array'],
                'documents.*.files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            ]);

            $relationship  = Relationship::query()->findOrFail($validated['relationship_id']);
            $age           = Carbon::parse($validated['birthdate'])->age;
            $documentsRaw  = $validated['documents'] ?? [];

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

            $createdMemberId = null;

            DB::transaction(function () use ($validated, $membership, $relationship, &$createdMemberId) {
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

                $createdMemberId = $member->id;
            });

            // Subir documentos fuera de la transacción para evitar rollbacks por fallos de storage
            if ($createdMemberId && !empty($documentsRaw)) {
                $this->uploadMemberDocuments([$createdMemberId => $documentsRaw]);
            }

            return redirect()
                ->route('members.manage.show', $membership)
                ->with('success', 'El familiar se agregó correctamente a la cuenta.');
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
            'separationReasons' => $this->buildSeparationReasonOptions(),
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
                'separation_reason_id' => ['nullable', new ExistsInSchema('memberships', 'separation_reasons', 'id')],
                'reason' => ['nullable', 'string', 'max:255'],
                'reason_document' => ['nullable', 'file'],
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

            $selectedSeparationReason = null;

            if (!empty($validated['separation_reason_id'])) {
                $selectedSeparationReason = SeparationReason::query()
                    ->with('documentType')
                    ->where('is_active', true)
                    ->findOrFail($validated['separation_reason_id']);

                if (
                    $selectedSeparationReason->relationship_id
                    && (int) $selectedSeparationReason->relationship_id !== (int) $accountMember->relationship_id
                ) {
                    throw ValidationException::withMessages([
                        'separation_reason_id' => 'El motivo seleccionado no aplica para este integrante.',
                    ]);
                }

                if ($selectedSeparationReason->requires_document && !$request->hasFile('reason_document')) {
                    throw ValidationException::withMessages([
                        'reason_document' => 'Debes cargar el documento requerido para este motivo.',
                    ]);
                }

                if ($selectedSeparationReason->requires_document && !$selectedSeparationReason->document_type_id) {
                    throw ValidationException::withMessages([
                        'reason_document' => 'El motivo seleccionado no tiene un tipo de documento configurado.',
                    ]);
                }

                if ($request->hasFile('reason_document')) {
                    $documentType = $selectedSeparationReason->documentType;
                    $allowedExtensions = collect(explode(',', $documentType?->allowed_extensions ?: 'pdf,jpg,png'))
                        ->map(fn ($extension) => strtolower(trim($extension)))
                        ->filter()
                        ->values()
                        ->all();
                    $fileExtension = strtolower($request->file('reason_document')->getClientOriginalExtension());

                    if (!in_array($fileExtension, $allowedExtensions, true)) {
                        throw ValidationException::withMessages([
                            'reason_document' => 'Solo se permiten archivos con extensión: ' . implode(', ', $allowedExtensions),
                        ]);
                    }

                    $maxFileSizeKb = $documentType?->max_file_size_kb ?: 5120;
                    if (($request->file('reason_document')->getSize() / 1024) > $maxFileSizeKb) {
                        throw ValidationException::withMessages([
                            'reason_document' => 'El archivo supera el tamaño máximo permitido.',
                        ]);
                    }
                }
            }

            $targetMembershipType = MembershipType::findOrFail($validated['target_membership_type_id']);
            $titularRelationshipId = Relationship::query()
                ->where('name', 'Titular')
                ->value('id');
            $reason = $selectedSeparationReason?->name
                ?? ($validated['reason'] ?? 'Separación de integrante a cuenta nueva');
            $reasonDocument = $request->file('reason_document');

            // If the member being separated already holds their own primary membership in
            // another club, reuse their existing account group so synchronizeMembershipFees
            // can distribute the interclub fee across both accounts (50/50 split).
            $existingPrimaryMembership = Membership::query()
                ->where('status', 'active')
                ->where('is_primary', true)
                ->where('club_id', '!=', $membership->club_id)
                ->whereHas('account.accountMembers', function (Builder $q) use ($accountMember) {
                    $q->where('member_id', $accountMember->member_id)
                      ->where('is_primary_holder', true);
                })
                ->with('account.accountGroup')
                ->first();

            $existingAccountGroup = $existingPrimaryMembership?->account?->accountGroup;

            DB::transaction(function () use ($membership, $accountMember, $targetMembershipType, $selectedTargetOption, $titularRelationshipId, $reason, $existingAccountGroup) {
                $newAccount = $this->createMembershipAccount(
                    club: $membership->club,
                    accountType: $targetMembershipType->allows_multiple_members ? 'family' : 'individual',
                    status: 'active',
                    accountGroup: $existingAccountGroup,
                    originAccountId: $membership->membership_account_id,
                    separationReason: $reason
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
                    'monthly_fee_total' => $selectedTargetOption['monthly_fee'],
                    'monthly_fee_share' => $selectedTargetOption['monthly_fee'],
                    'billing_split_mode' => $selectedTargetOption['billing_split_mode'] ?? 'single',
                    'start_date' => now()->toDateString(),
                    'end_date' => $targetMembershipType->validity_months
                        ? now()->addMonthsNoOverflow($targetMembershipType->validity_months)->toDateString()
                        : null,
                    'status' => 'active',
                ]);

                $newMembership = $this->membershipChargeService
                    ->synchronizeMembershipFees(
                        $newMembership,
                        (float) $selectedTargetOption['monthly_fee'],
                        null,
                        $selectedTargetOption['billing_split_mode'] ?? 'single'
                    )
                    ->firstWhere('id', $newMembership->id) ?? $newMembership->fresh(['membershipType', 'account.primaryHolder']);

                $this->membershipChargeService->createInitialCharges(
                    membership: $newMembership,
                    monthlyFee: (float) $selectedTargetOption['monthly_fee'],
                    inscriptionFee: 0.0, // separations are internal transfers, not new enrollments
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

            if ($selectedSeparationReason?->requires_document && $reasonDocument) {
                $this->uploadMemberDocuments([
                    $accountMember->member_id => [
                        [
                            'document_type_id' => $selectedSeparationReason->document_type_id,
                            'files' => [$reasonDocument],
                        ],
                    ],
                ]);
            }

            return redirect()->route('members.index')->with('success', 'El integrante fue separado correctamente en una nueva cuenta.');
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al separar al integrante.',
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
                    'messageError' => 'No hay un club seleccionado en la sesión.',
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
                'internal_account_number' => [
                    'nullable',
                    'string',
                    'max:100',
                    new UniqueInSchema('memberships', 'accounts', 'internal_account_number'),
                ],
                'inscription_fee_override' => ['nullable', 'numeric', 'min:0'],
                'inscription_discount_document' => [
                    'nullable',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                    'required_with:inscription_fee_override',
                ],
                'installment_months' => ['nullable', 'integer', 'min:1', 'max:60'],
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
                'members.*.address.years_in_city' => ['nullable', 'integer', 'min:0', 'max:999'],
                'members.*.employment' => ['nullable', 'array'],
                'members.*.employment.company_name' => ['nullable', 'string', 'max:255'],
                'members.*.employment.company_address' => ['nullable', 'string', 'max:255'],
                'members.*.employment.company_phone' => ['nullable', 'string', 'max:50'],
                // Documentos
                'members.*.documents'                       => ['nullable', 'array'],
                'members.*.documents.*.document_type_id'   => ['required_with:members.*.documents.*', 'integer'],
                'members.*.documents.*.files'               => ['nullable', 'array'],
                'members.*.documents.*.files.*'             => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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
            $internalAccountNumber = $validated['internal_account_number'] ?? null;
            $inscriptionFeeOverride = isset($validated['inscription_fee_override'])
                ? (float) $validated['inscription_fee_override']
                : null;
            $inscriptionDiscountDocument = $request->file('inscription_discount_document');
            $installmentMonths = isset($validated['installment_months'])
                ? (int) $validated['installment_months']
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

                $sourcePrimaryHolderId = $sourceMembership->account?->primaryHolder?->member_id;

                if (
                    $sourcePrimaryHolderId
                    && $this->memberHasOtherActiveClubMembership((int) $sourcePrimaryHolderId, (int) $clubId)
                ) {
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
                $reusableSourceMemberIds = $this->resolveReusableSourceMemberIds($sourceMembership)
                    ->map(fn ($id) => (int) $id)
                    ->all();
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
                    if (!empty($memberData['id']) && !in_array((int) $memberData['id'], $reusableSourceMemberIds, true)) {
                        return redirect()->back()->withErrors([
                            'messageError' => "El integrante seleccionado en la posición " . ($index + 1) . " no pertenece al grupo familiar de origen.",
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
                    'messageError' => 'La membresía seleccionada no permite múltiples integrantes.',
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
            $reusableSourceMemberIds = $sourceMembership
                ? $this->resolveReusableSourceMemberIds($sourceMembership)
                    ->map(fn ($id) => (int) $id)
                    ->all()
                : [];

            // Collect [member_id => documents[]] inside the transaction to upload after commit
            $savedMemberDocuments    = [];
            $savedMembershipAccount  = null;
            $savedPrimaryMemberId    = null;

            DB::transaction(function () use ($validated, $membershipType, $pricing, $clubId, $club, $fromMembershipType, $sourceMembership, $sameClubTransition, $sourceAccountMembersById, $reusableSourceMemberIds, $internalAccountNumber, $inscriptionFeeOverride, $installmentMonths, &$savedMemberDocuments, &$savedMembershipAccount, &$savedPrimaryMemberId) {
                $sourceAccount = $sourceMembership?->account;

                $membershipAccount = $sameClubTransition
                    ? tap($sourceAccount)->update([
                        'account_type' => $membershipType->allows_multiple_members ? 'family' : 'individual',
                        'status' => 'active',
                        'internal_account_number' => $internalAccountNumber,
                    ])
                    : $this->createMembershipAccount(
                        club: $club,
                        accountType: $membershipType->allows_multiple_members ? 'family' : 'individual',
                        status: 'active',
                        accountGroup: $sourceAccount?->accountGroup,
                        internalAccountNumber: $internalAccountNumber,
                    );

                $savedMembershipAccount = $membershipAccount;

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

                    if ($existingMember && in_array((int) $existingMember->id, $reusableSourceMemberIds, true)) {
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

                    if (!empty($memberData['is_primary_holder'])) {
                        $savedPrimaryMemberId = $member->id;
                    }

                    // Queue documents for SFTP upload after transaction commits
                    if (!empty($memberData['documents'])) {
                        $savedMemberDocuments[$member->id] = $memberData['documents'];
                    }

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
                        'monthly_fee_total' => $pricing['monthly_fee'],
                        'monthly_fee_share' => $pricing['monthly_fee'],
                        'billing_split_mode' => $pricing['billing_split_mode'] ?? 'single',
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
                        'reason' => 'Cambio de tipo de membresía',
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

                    $sourceMembership = $this->membershipChargeService
                        ->synchronizeMembershipFees(
                            $sourceMembership,
                            (float) $pricing['monthly_fee'],
                            null,
                            $pricing['billing_split_mode'] ?? 'single'
                        )
                        ->firstWhere('id', $sourceMembership->id) ?? $sourceMembership->fresh(['membershipType', 'account.primaryHolder']);

                    $this->membershipChargeService->createInitialCharges(
                        membership: $sourceMembership,
                        monthlyFee: (float) $pricing['monthly_fee'],
                        inscriptionFee: $inscriptionFeeOverride ?? (float) ($pricing['inscription_fee'] ?? 0),
                        metadata: [
                            'charge_origin' => 'same_account_transition',
                            'previous_membership_type_id' => $previousMembershipTypeId,
                            'new_membership_type_id' => $membershipType->id,
                            'inscription_fee_override' => $inscriptionFeeOverride,
                        ],
                        chargeDate: now(),
                        reconcileExistingMonthlyCharge: true,
                        installmentMonths: $installmentMonths,
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
                    'monthly_fee_total' => $pricing['monthly_fee'],
                    'monthly_fee_share' => $pricing['monthly_fee'],
                    'billing_split_mode' => $pricing['billing_split_mode'] ?? 'single',
                    'start_date' => now()->toDateString(),
                    'end_date' => $membershipType->validity_months
                        ? now()->addMonthsNoOverflow($membershipType->validity_months)->toDateString()
                        : null,
                    'status' => 'active',
                ]);

                DB::table('memberships.membership_history')->insert([
                    'membership_id'          => $newMembership->id,
                    'old_membership_type_id' => $sourceMembership?->membership_type_id ?? null,
                    'new_membership_type_id' => $membershipType->id,
                    'changed_by'             => auth()->id(),
                    'effective_date'         => now()->toDateString(),
                    'reason'                 => $sourceMembership ? 'Membresía adicional / traslado interclub' : 'Alta de membresía',
                    'previous_monthly_fee'   => $sourceMembership ? (float) $sourceMembership->monthly_fee : null,
                    'new_monthly_fee'        => (float) $pricing['monthly_fee'],
                    'metadata'               => json_encode([
                        'charge_origin'          => $sourceMembership ? 'additional_membership' : 'membership_registration',
                        'source_membership_id'   => $sourceMembership?->id,
                    ]),
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);

                // Cargar account para que synchronizeMembershipFees pueda encontrar
                // el account_group_id y actualizar todas las membresías del grupo.
                // Sin esto, el servicio trata la nueva membresía como standalone y
                // sobreescribe billing_split_mode a 'single'.
                $newMembership->load('account');

                $newMembership = $this->membershipChargeService
                    ->synchronizeMembershipFees(
                        $newMembership,
                        (float) $pricing['monthly_fee'],
                        null,
                        $pricing['billing_split_mode'] ?? 'single'
                    )
                    ->firstWhere('id', $newMembership->id) ?? $newMembership->fresh(['membershipType', 'account.primaryHolder']);

                $this->membershipChargeService->createInitialCharges(
                    membership: $newMembership,
                    monthlyFee: (float) $pricing['monthly_fee'],
                    inscriptionFee: $inscriptionFeeOverride ?? (float) ($pricing['inscription_fee'] ?? 0),
                    metadata: [
                        'charge_origin' => $sourceMembership ? 'additional_membership' : 'membership_registration',
                        'source_membership_id' => $sourceMembership?->id,
                        'inscription_fee_override' => $inscriptionFeeOverride,
                    ],
                    chargeDate: now(),
                    reconcileExistingMonthlyCharge: (bool) ($sourceMembership && ($pricing['source_membership_becomes_non_billable'] ?? false)),
                    installmentMonths: $installmentMonths,
                );

                if ($sourceMembership && ($pricing['source_membership_becomes_non_billable'] ?? false)) {
                    $sourceMembership->update([
                        'is_billable' => false,
                    ]);
                }
            });

            // ── Upload documents to Spaces after transaction commits ──────────
            $this->uploadMemberDocuments(
                memberDocuments: $savedMemberDocuments,
            );

            if ($inscriptionDiscountDocument && $savedPrimaryMemberId) {
                $discountDocType = \App\Models\Catalogs\DocumentType::where('code', 'INSCRIPTION_DISCOUNT')->first();

                if ($discountDocType) {
                    $this->uploadMemberDocuments([
                        $savedPrimaryMemberId => [
                            [
                                'document_type_id' => $discountDocType->id,
                                'files'            => [$inscriptionDiscountDocument],
                            ],
                        ],
                    ]);
                }
            }

            return redirect()
                ->route('members.index')
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

    /**
     * Upload member documents to Spaces and persist records in members.documents.
     * File failures are logged but do not abort the response.
     *
     * Path: members/{member_id}/{document_type_slug}/{uuid}.{ext}
     *
     * @param array<int, array> $memberDocuments  [member_id => documents[]]
     */
    protected function uploadMemberDocuments(
        array $memberDocuments,
    ): void {
        if (empty($memberDocuments)) {
            return;
        }

        $docTypeSlugCache = [];

        foreach ($memberDocuments as $memberId => $documents) {
            foreach ($documents as $docData) {
                $documentTypeId = $docData['document_type_id'] ?? null;
                $files          = $docData['files'] ?? [];

                if (!$documentTypeId || empty($files)) {
                    continue;
                }

                if (!isset($docTypeSlugCache[$documentTypeId])) {
                    $docType = DocumentType::find($documentTypeId);
                    $docTypeSlugCache[$documentTypeId] = $docType
                        ? Str::slug($docType->name)
                        : (string) $documentTypeId;
                }

                $docTypeSlug = $docTypeSlugCache[$documentTypeId];
                $directory   = "members/{$memberId}/{$docTypeSlug}";

                foreach ($files as $file) {
                    if (!($file instanceof \Illuminate\Http\UploadedFile)) {
                        continue;
                    }

                    try {
                        $extension = $file->getClientOriginalExtension();
                        $filename  = Str::uuid() . '.' . $extension;

                        $uploaded = Storage::disk('spaces')->putFileAs($directory, $file, $filename);

                        if ($uploaded === false) {
                            Log::error('Spaces document upload returned false (archivo no subido)', [
                                'member_id'        => $memberId,
                                'document_type_id' => $documentTypeId,
                                'file'             => $file->getClientOriginalName(),
                            ]);
                            continue;
                        }

                        MemberDocument::create([
                            'member_id'        => $memberId,
                            'document_type_id' => $documentTypeId,
                            'file_path'        => "{$directory}/{$filename}",
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Spaces document upload failed', [
                            'member_id'        => $memberId,
                            'document_type_id' => $documentTypeId,
                            'file'             => $file->getClientOriginalName(),
                            'error'            => $e->getMessage(),
                        ]);
                    }
                }
            }
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
            'account.club',
            'account.accountGroup.absencePermits',
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
            'account.accountMembers.member.documents.documentType',
            'membershipType.documentTypes.relationships',
            'club',
            'account.memberships.membershipType',
            'account.memberships.club',
        ]);

        return $membership;
    }

    public function editMember(Request $request, Membership $membership, Member $member)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership = $this->loadMembershipContext($membership);

        $accountMember = $membership->account->accountMembers
            ->firstWhere('member_id', $member->id);

        if (!$accountMember) {
            abort(404);
        }

        $member->load(['primaryAddress', 'employmentInfo', 'birthCountry', 'birthState', 'birthCity', 'nationality', 'maritalStatus']);

        return Inertia::render('Members/EditMember', [
            'membership' => $this->buildSourceMembershipPayload($membership),
            'accountMember' => [
                'member_id'        => $member->id,
                'is_primary_holder' => (bool) $accountMember->is_primary_holder,
                'relationship_id'  => $accountMember->relationship_id,
                'first_name'       => $member->first_name,
                'last_name'        => $member->last_name,
                'second_last_name' => $member->second_last_name,
                'birthdate'        => $member->birthdate,
                'phone'            => $member->phone,
                'email'            => $member->email,
                'birth_country_id' => $member->birth_country_id,
                'birth_state_id'   => $member->birth_state_id,
                'birth_city_id'    => $member->birth_city_id,
                'nationality_id'   => $member->nationality_id,
                'marital_status_id' => $member->marital_status_id,
                'occupation'       => $member->occupation,
                'school_name'      => $member->school_name,
                'address' => [
                    'street'       => $member->primaryAddress?->street,
                    'neighborhood' => $member->primaryAddress?->neighborhood,
                    'postal_code'  => $member->primaryAddress?->postal_code,
                    'country_id'   => $member->primaryAddress?->country_id,
                    'state_id'     => $member->primaryAddress?->state_id,
                    'city_id'      => $member->primaryAddress?->city_id,
                    'years_in_city' => $member->primaryAddress?->years_in_city,
                ],
                'employment' => [
                    'company_name'    => $member->employmentInfo?->company_name,
                    'company_address' => $member->employmentInfo?->company_address,
                    'company_phone'   => $member->employmentInfo?->company_phone,
                ],
            ],
            ...$this->getCreateFormCatalogs(),
        ]);
    }

    public function updateMember(Request $request, Membership $membership, Member $member)
    {
        try {
            $clubId = session('club_id');

            if ((int) $membership->club_id !== (int) $clubId) {
                abort(404);
            }

            $membership = $this->loadMembershipContext($membership);

            $accountMember = $membership->account->accountMembers
                ->firstWhere('member_id', $member->id);

            if (!$accountMember) {
                abort(404);
            }

            $validated = $request->validate([
                'first_name'       => ['required', 'string', 'max:100'],
                'last_name'        => ['required', 'string', 'max:100'],
                'second_last_name' => ['nullable', 'string', 'max:100'],
                'birthdate'        => ['required', 'date'],
                'phone'            => ['nullable', 'string', 'max:20'],
                'email'            => ['nullable', 'email', 'max:150'],
                'birth_country_id' => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'birth_state_id'   => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'birth_city_id'    => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'nationality_id'   => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'marital_status_id' => ['nullable', new ExistsInSchema('catalogs', 'marital_statuses', 'id')],
                'occupation'       => ['nullable', 'string', 'max:150'],
                'school_name'      => ['nullable', 'string', 'max:150'],
                'relationship_id'  => ['nullable', new ExistsInSchema('catalogs', 'relationships', 'id')],
                'address.street'       => ['nullable', 'string', 'max:200'],
                'address.neighborhood' => ['nullable', 'string', 'max:200'],
                'address.postal_code'  => ['nullable', 'string', 'max:20'],
                'address.country_id'   => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'address.state_id'     => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'address.city_id'      => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'address.years_in_city' => ['nullable', 'integer', 'min:0'],
                'employment.company_name'    => ['nullable', 'string', 'max:200'],
                'employment.company_address' => ['nullable', 'string', 'max:200'],
                'employment.company_phone'   => ['nullable', 'string', 'max:20'],
            ]);

            DB::transaction(function () use ($validated, $member, $accountMember) {
                $member->update([
                    'first_name'        => $validated['first_name'],
                    'last_name'         => $validated['last_name'],
                    'second_last_name'  => $validated['second_last_name'] ?? null,
                    'birthdate'         => $validated['birthdate'],
                    'phone'             => $validated['phone'] ?? null,
                    'email'             => $validated['email'] ?? null,
                    'birth_country_id'  => $validated['birth_country_id'] ?? null,
                    'birth_state_id'    => $validated['birth_state_id'] ?? null,
                    'birth_city_id'     => $validated['birth_city_id'] ?? null,
                    'nationality_id'    => $validated['nationality_id'] ?? null,
                    'marital_status_id' => $validated['marital_status_id'] ?? null,
                    'occupation'        => $validated['occupation'] ?? null,
                    'school_name'       => $validated['school_name'] ?? null,
                ]);

                if (!$accountMember->is_primary_holder && !empty($validated['relationship_id'])) {
                    $accountMember->update(['relationship_id' => $validated['relationship_id']]);
                }

                $addressData = array_filter($validated['address'] ?? [], fn($v) => $v !== null);
                if (!empty($addressData)) {
                    $member->addresses()->updateOrCreate(
                        ['is_primary' => true],
                        array_merge($addressData, ['is_primary' => true])
                    );
                }

                $employmentData = array_filter($validated['employment'] ?? [], fn($v) => $v !== null);
                if (!empty($employmentData)) {
                    $member->employmentInfo()->updateOrCreate(
                        ['member_id' => $member->id],
                        $employmentData
                    );
                }
            });

            return redirect()
                ->route('members.manage.show', $membership)
                ->with('success', 'Información del integrante actualizada correctamente.');
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la información del integrante.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function getCreateFormCatalogs(): array
    {
        $countries = Country::select('id', 'iso2 as code', 'name', 'translations', 'demonym')
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
        return $this->resolveReusableSourceAccountMembers($membership)
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
                    'birth_place' => $this->getCountryDisplayName($member?->birthCountry) ?? $member?->birth_place,
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
                        'country_id' => $address?->country_id,
                        'state_id' => $address?->state_id,
                        'city_id' => $address?->city_id,
                        'years_in_city' => $address?->years_in_city,
                    ],
                    'employment' => [
                        'company_name' => $employment?->company_name,
                        'company_address' => $employment?->company_address,
                        'company_phone' => $employment?->company_phone,
                    ],
                    'existing_documents' => $member?->documents
                        ->sortByDesc('created_at')
                        ->map(fn ($doc) => [
                            'id' => $doc->id,
                            'document_type_id' => $doc->document_type_id,
                            'uploaded_at' => $doc->created_at?->toDateString(),
                        ])
                        ->unique('document_type_id')
                        ->values(),
                ];
            })
            ->values();
    }

    protected function resolveReusableSourceAccountMembers(Membership $membership)
    {
        $sourceAccountMembers = $membership->account?->accountMembers
            ? $membership->account->accountMembers
                ->filter(fn (MembershipAccountMember $accountMember) => !empty($accountMember->member_id))
                ->keyBy('member_id')
            : collect();

        $accountGroupId = $membership->account?->account_group_id;

        if (!$accountGroupId) {
            return $sourceAccountMembers->values();
        }

        $groupAccountMembers = MembershipAccountMember::query()
            ->with([
                'relationship',
                'member.primaryAddress.country',
                'member.primaryAddress.state',
                'member.primaryAddress.city',
                'member.employmentInfo',
                'member.nationality',
                'member.birthCountry',
                'member.birthState',
                'member.birthCity',
                'member.maritalStatus',
                'member.documents',
            ])
            ->whereHas('membershipAccount', function (Builder $query) use ($accountGroupId) {
                $query->where('account_group_id', $accountGroupId)
                    ->whereHas('memberships', function (Builder $membershipQuery) {
                        $membershipQuery->where('status', 'active')
                            ->where('is_primary', true);
                    });
            })
            ->get()
            ->filter(fn (MembershipAccountMember $accountMember) => !empty($accountMember->member_id))
            ->keyBy('member_id');

        return $sourceAccountMembers
            ->union($groupAccountMembers)
            ->values();
    }

    protected function resolveReusableSourceMemberIds(Membership $membership)
    {
        $sourceAccountMemberIds = $membership->account?->accountMembers
            ? $membership->account->accountMembers
                ->pluck('member_id')
                ->filter()
                ->values()
            : collect();

        $accountGroupId = $membership->account?->account_group_id;

        if (!$accountGroupId) {
            return $sourceAccountMemberIds;
        }

        $groupMemberIds = MembershipAccountMember::query()
            ->whereHas('membershipAccount', function (Builder $query) use ($accountGroupId) {
                $query->where('account_group_id', $accountGroupId)
                    ->whereHas('memberships', function (Builder $membershipQuery) {
                        $membershipQuery->where('status', 'active')
                            ->where('is_primary', true);
                    });
            })
            ->pluck('member_id')
            ->filter()
            ->unique()
            ->values();

        return $sourceAccountMemberIds
            ->merge($groupMemberIds)
            ->unique()
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
            'birth_place' => $this->getCountryDisplayName($country) ?? ($payload['birth_place'] ?? null),
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
            'country_id' => $country?->id,
            'state_id' => $state?->id,
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
            $errors[$countryAttribute] = 'Selecciona un país antes de seleccionar un estado.';
        }

        if ($state && $country && (int) $state->country_id !== (int) $country->id) {
            $errors[$stateAttribute] = 'El estado seleccionado no pertenece al país indicado.';
        }

        if ($city && !$state) {
            $errors[$stateAttribute] = 'Selecciona un estado antes de seleccionar una ciudad.';
        }

        if ($city && $state && (int) $city->state_id !== (int) $state->id) {
            $errors[$cityAttribute] = 'La ciudad seleccionada no pertenece al estado indicado.';
        }

        if ($city && $country && (int) $city->country_id !== (int) $country->id) {
            $errors[$cityAttribute] = 'La ciudad seleccionada no pertenece al país indicado.';
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
            'relationship_id' => $accountMember->relationship_id,
            'relationship_name' => $accountMember->relationship?->name,
            'email' => $member?->email,
            'phone' => $member?->phone,
            'is_primary_holder' => (bool) $accountMember->is_primary_holder,
        ];
    }

    protected function buildMembershipAccountPayload(Membership $membership): array
    {
        $this->syncAbsencePermitStatuses($membership->account?->accountGroup);

        $activeMemberships = $membership->account->memberships
            ->where('status', 'active')
            ->where('is_primary', true)
            ->values();
        $billableMembership = $activeMemberships->firstWhere('is_billable', true);
        $accountClub = $membership->account?->club ?? $activeMemberships->first()?->club;
        $absencePermits = $membership->account?->accountGroup?->absencePermits
            ? $membership->account->accountGroup->absencePermits
                ->sortByDesc('start_date')
                ->values()
            : collect();
        $currentAbsencePermit = $this->resolveCurrentAbsencePermit($absencePermits);
        $billableMonthlyTotal = (float) $activeMemberships
            ->sum(fn (Membership $activeMembership) => $activeMembership->resolved_monthly_fee_share);

        return [
            'id' => $membership->account?->id,
            'membership_number' => $membership->account?->membership_number,
            'internal_account_number' => $membership->account?->internal_account_number,
            'account_club_name' => $accountClub?->name,
            'account_club_code' => $accountClub?->code,
            'account_type' => $membership->account?->account_type,
            'status' => $membership->account?->status,
            'current_monthly_fee' => (float) $activeMemberships->sum(fn (Membership $activeMembership) => $activeMembership->resolved_monthly_fee_share),
            'absence_permit_preview_fee' => $currentAbsencePermit
                ? round($billableMonthlyTotal * ((float) $currentAbsencePermit->charge_percentage / 100), 2)
                : null,
            'current_absence_permit' => $currentAbsencePermit
                ? $this->buildAbsencePermitPayload($currentAbsencePermit)
                : null,
            'absence_permits' => $absencePermits
                ->map(fn (AbsencePermit $absencePermit) => $this->buildAbsencePermitPayload($absencePermit))
                ->values(),
            'primary_holder' => $this->buildDetailedAccountMemberPayload(
                $membership->account?->primaryHolder,
                $membership->membershipType?->documentTypes ?? collect(),
            ),
            'members' => $membership->account->accountMembers
                ->sortByDesc('is_primary_holder')
                ->map(fn(MembershipAccountMember $accountMember) => $this->buildDetailedAccountMemberPayload(
                    $accountMember,
                    $membership->membershipType?->documentTypes ?? collect(),
                ))
                ->values(),
            'active_memberships' => $activeMemberships
                ->map(function (Membership $activeMembership) {
                    return [
                        'id' => $activeMembership->id,
                        'membership_type_name' => $activeMembership->membershipType?->name,
                        'membership_type_code' => $activeMembership->membershipType?->code,
                        'club_name' => $activeMembership->club?->name,
                        'club_code' => $activeMembership->club?->code,
                        'monthly_fee' => (float) $activeMembership->resolved_monthly_fee_share,
                        'monthly_fee_total' => (float) $activeMembership->resolved_monthly_fee_total,
                        'monthly_fee_share' => (float) $activeMembership->resolved_monthly_fee_share,
                        'billing_split_mode' => $activeMembership->billing_split_mode,
                        'is_billable' => (bool) $activeMembership->is_billable,
                        'status' => $activeMembership->status,
                        'start_date' => $activeMembership->start_date,
                        'end_date' => $activeMembership->end_date,
                    ];
                })
                ->values(),
        ];
    }

    protected function buildAbsencePermitPayload(AbsencePermit $absencePermit): array
    {
        return [
            'id' => $absencePermit->id,
            'start_date' => $absencePermit->start_date,
            'end_date' => $absencePermit->end_date,
            'charge_percentage' => (float) $absencePermit->charge_percentage,
            'status' => $absencePermit->status,
            'blocks_facility_access' => (bool) $absencePermit->blocks_facility_access,
            'blocks_reservations' => (bool) $absencePermit->blocks_reservations,
            'notes' => $absencePermit->notes,
            'approved_at' => optional($absencePermit->approved_at)?->toDateTimeString(),
        ];
    }

    protected function resolveCurrentAbsencePermit($absencePermits): ?AbsencePermit
    {
        $today = now()->startOfDay()->toDateString();

        return $absencePermits
            ->first(function (AbsencePermit $absencePermit) use ($today) {
                return in_array($absencePermit->status, ['approved', 'active'], true)
                    && $absencePermit->start_date <= $today
                    && $absencePermit->end_date >= $today;
            });
    }

    protected function resolveAbsencePermitStatus(Carbon $startDate, Carbon $endDate): string
    {
        $today = now()->startOfDay();

        if ($endDate->lt($today)) {
            return 'finished';
        }

        if ($startDate->gt($today)) {
            return 'approved';
        }

        return 'active';
    }

    protected function syncAbsencePermitStatuses(?MembershipAccountGroup $accountGroup): void
    {
        if (!$accountGroup || !$accountGroup->relationLoaded('absencePermits')) {
            return;
        }

        $today = now()->startOfDay();

        $accountGroup->absencePermits->each(function (AbsencePermit $absencePermit) use ($today) {
            $newStatus = $absencePermit->status;
            $startDate = Carbon::parse($absencePermit->start_date)->startOfDay();
            $endDate = Carbon::parse($absencePermit->end_date)->startOfDay();

            if ($absencePermit->status === 'cancelled') {
                return;
            }

            if ($endDate->lt($today)) {
                $newStatus = 'finished';
            } elseif ($startDate->gt($today)) {
                $newStatus = 'approved';
            } else {
                $newStatus = 'active';
            }

            if ($newStatus !== $absencePermit->status) {
                $absencePermit->forceFill(['status' => $newStatus])->save();
                $absencePermit->status = $newStatus;
            }
        });
    }

    public function updateInternalAccountNumber(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $account = $membership->account;

        if (!$account) {
            abort(404);
        }

        $validated = $request->validate([
            'internal_account_number' => [
                'nullable',
                'string',
                'max:100',
                new UniqueInSchema(
                    'memberships',
                    'accounts',
                    'internal_account_number',
                    $account->id,
                    'id'
                ),
            ],
        ]);

        $account->update([
            'internal_account_number' => $validated['internal_account_number'] ?? null,
        ]);

        return redirect()
            ->route('members.manage.show', $membership)
            ->with('success', 'Número de cuenta interno actualizado correctamente.');
    }

    public function storeDocument(Request $request, Membership $membership)
    {
        try {
            $clubId = session('club_id');

            if ((int) $membership->club_id !== (int) $clubId) {
                abort(404);
            }

            $validated = $request->validate([
                'member_id'        => ['required', 'integer'],
                'document_type_id' => ['required', 'integer'],
                'files'            => ['required', 'array', 'min:1'],
                'files.*'          => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ], [
                'files.required'   => 'El documento es obligatorio.',
                'files.*.mimes'    => 'El documento debe ser PDF, JPG o PNG.',
                'files.*.max'      => 'El documento no debe superar los 5 MB.',
            ]);

            $memberId       = (int) $validated['member_id'];
            $documentTypeId = (int) $validated['document_type_id'];

            $isMemberOfAccount = $membership->account->accountMembers
                ->contains('member_id', $memberId);

            if (!$isMemberOfAccount) {
                abort(403, 'El integrante no pertenece a esta cuenta.');
            }

            $docType     = DocumentType::findOrFail($documentTypeId);
            $docTypeSlug = \Illuminate\Support\Str::slug($docType->name);
            $directory   = "members/{$memberId}/{$docTypeSlug}";
            $userId      = $request->user()?->id;

            foreach ($request->file('files') as $file) {
                $filename = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();

                $uploaded = \Illuminate\Support\Facades\Storage::disk('spaces')
                    ->putFileAs($directory, $file, $filename);

                if ($uploaded === false) {
                    throw new \RuntimeException('No se pudo subir el documento.');
                }

                MemberDocument::create([
                    'member_id'        => $memberId,
                    'document_type_id' => $documentTypeId,
                    'file_path'        => "{$directory}/{$filename}",
                    'uploaded_by'      => $userId,
                ]);
            }

            return redirect()
                ->route('members.manage.show', $membership)
                ->with('success', 'Documento cargado correctamente.');
        } catch (ValidationException $e) {
            return $this->validationExceptionResponse($e);
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al subir el documento.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    protected function buildDetailedAccountMemberPayload(?MembershipAccountMember $accountMember, $documentTypes = null): ?array
    {
        if (!$accountMember) {
            return null;
        }

        $member = $accountMember->member;
        $address = $member?->primaryAddress;
        $employment = $member?->employmentInfo;

        $lockerMembers = LockerAssignment::with('locker')
            ->where('member_id', $member->id)
            ->where('year', now()->year)
            ->where('club_id', session('club_id'))
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'locker_id' => $assignment->locker_id,
                    'number' => $assignment->locker->number,
                    'status' => $assignment->locker->status,
                    'category' => $assignment->locker->category,
                ];
            });

        $uploadedDocs = $member?->documents ?? collect();
        $relationshipId = $accountMember->is_primary_holder ? 1 : $accountMember->relationship_id;
        $memberAge = $member?->birthdate ? Carbon::parse($member->birthdate)->age : null;

        $documents = collect($documentTypes ?? [])
            ->filter(fn ($docType) => $docType->relationships->contains('id', $relationshipId))
            ->filter(function ($docType) use ($memberAge) {
                if ($memberAge === null) return true;
                if ($docType->min_age !== null && $memberAge < (int) $docType->min_age) return false;
                if ($docType->max_age !== null && $memberAge > (int) $docType->max_age) return false;
                return true;
            })
            ->map(function ($docType) use ($uploadedDocs) {
                $allowMultiple = (bool) $docType->pivot->allow_multiple;
                $numberFiles   = (int) $docType->pivot->number_files;

                $docsForType = $uploadedDocs
                    ->where('document_type_id', $docType->id)
                    ->map(fn ($d) => [
                        'id'          => $d->id,
                        'uploaded_at' => $d->created_at?->toDateString(),
                    ])
                    ->values();

                $requiredCount   = $allowMultiple ? $numberFiles : 1;
                $alreadyUploaded = $docsForType->count() >= $requiredCount;

                return [
                    'document_type_id'   => $docType->id,
                    'name'               => $docType->name,
                    'allowed_extensions' => $docType->allowed_extensions
                        ? collect(explode(',', $docType->allowed_extensions))->map(fn ($e) => trim(strtolower($e)))->values()
                        : [],
                    'max_file_size_kb'   => $docType->max_file_size_kb !== null ? (int) $docType->max_file_size_kb : null,
                    'is_required'        => (bool) $docType->pivot->is_required,
                    'allow_multiple'     => $allowMultiple,
                    'number_files'       => $numberFiles,
                    'already_uploaded'   => $alreadyUploaded,
                    'uploaded_docs'      => $docsForType,
                ];
            });

            $lockerAssignment = LockerAssignment::where('member_id', $member->id)->latest()->first();

            if ($lockerAssignment && $lockerAssignment->file_path) {

                $documents->push([
                    'document_type_id'   => 'locker_assignment',
                    'name'               => 'Comprobante de Casillero',
                    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
                    'max_file_size_kb'   => 5120,
                    'is_required'        => false,
                    'allow_multiple'     => false,
                    'number_files'       => 1,
                    'already_uploaded'   => true,
                    'uploaded_docs'      => [
                        [
                            'id' => 'locker_' . $lockerAssignment->id,
                            'uploaded_at' => $lockerAssignment->created_at?->toDateString(),
                            'url' => Storage::disk('spaces')->temporaryUrl(
                                $lockerAssignment->file_path,
                                now()->addMinutes(30)
                            ),
                        ]
                    ],
                ]);
        }

        // ── Documentos extra: están en members.documents pero no son requeridos
        //    por el tipo de membresía (ej. comprobante de descuento de inscripción).
        $coveredDocTypeIds = $documents
            ->pluck('document_type_id')
            ->filter(fn ($id) => is_int($id))
            ->all();

        $uploadedDocs
            ->whereNotIn('document_type_id', $coveredDocTypeIds)
            ->groupBy('document_type_id')
            ->each(function ($docs) use (&$documents) {
                $docType = $docs->first()->documentType;
                if (!$docType) {
                    return;
                }

                $documents->push([
                    'document_type_id'   => $docType->id,
                    'name'               => $docType->name,
                    'allowed_extensions' => $docType->allowed_extensions
                        ? collect(explode(',', $docType->allowed_extensions))
                            ->map(fn ($e) => trim(strtolower($e)))
                            ->values()
                            ->all()
                        : [],
                    'max_file_size_kb'   => $docType->max_file_size_kb !== null
                        ? (int) $docType->max_file_size_kb
                        : null,
                    'is_required'        => false,
                    'allow_multiple'     => true,
                    'number_files'       => $docs->count(),
                    'already_uploaded'   => true,
                    'uploaded_docs'      => $docs
                        ->map(fn ($d) => [
                            'id'          => $d->id,
                            'uploaded_at' => $d->created_at?->toDateString(),
                        ])
                        ->values()
                        ->all(),
                ]);
            });

        $documents = $documents->values();

        return [
            ...$this->buildAccountMemberPayload($accountMember),
            'relationship_id' => $accountMember->relationship_id,
            'birthdate' => $member?->birthdate,
            'age' => $member?->birthdate ? Carbon::parse($member->birthdate)->age : null,
            'birth_place' => $this->getCountryDisplayName($member?->birthCountry) ?? $member?->birth_place,
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
                'city' => $address?->city?->name,
                'state' => $address?->state?->name,
                'country' => $this->getCountryDisplayName($address?->country),
                'years_in_city' => $address?->years_in_city,
            ],
            'employment' => [
                'company_name' => $employment?->company_name,
                'company_address' => $employment?->company_address,
                'company_phone' => $employment?->company_phone,
            ],
            'locker' => $lockerMembers->values(),
            'documents' => $documents,
        ];
    }

    protected function getCountryDisplayName(Country|string|null $country): ?string
    {
        if (!$country) {
            return null;
        }

        if (is_string($country)) {
            return $country;
        }

        $translations = is_array($country->translations) ? $country->translations : [];

        return $translations['es-MX']
            ?? $translations['es']
            ?? $country->name;
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

                $hasOtherClub = $this->memberHasOtherActiveClubMembership(
                    $accountMember->member_id,
                    $membership->club_id
                );

                $otherClubName = null;
                if ($hasOtherClub) {
                    $otherClubName = Membership::query()
                        ->where('status', 'active')
                        ->where('is_primary', true)
                        ->where('club_id', '!=', $membership->club_id)
                        ->whereHas('account.accountMembers', function (Builder $q) use ($accountMember) {
                            $q->where('member_id', $accountMember->member_id)
                              ->where('is_primary_holder', true);
                        })
                        ->with('club:id,name')
                        ->first()
                        ?->club
                        ?->name;
                }

                return [
                    ...$memberPayload,
                    'age' => $age,
                    'has_other_club_membership' => $hasOtherClub,
                    'other_club_name' => $otherClubName,
                    'target_membership_options' => $this->buildSeparationTargetOptions($membership, $accountMember)
                        ->values(),
                ];
            })
            ->filter(fn(array $candidate) => !empty($candidate['target_membership_options']))
            ->values();
    }

    protected function buildSeparationReasonOptions()
    {
        return SeparationReason::query()
            ->with(['relationship', 'documentType'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (SeparationReason $reason) {
                return [
                    'id' => $reason->id,
                    'code' => $reason->code,
                    'name' => $reason->name,
                    'relationship_id' => $reason->relationship_id,
                    'relationship_name' => $reason->relationship?->name,
                    'document_type_id' => $reason->document_type_id,
                    'document_type_code' => $reason->documentType?->code,
                    'document_type_name' => $reason->documentType?->name,
                    'allowed_extensions' => $reason->documentType?->allowed_extensions
                        ? collect(explode(',', $reason->documentType->allowed_extensions))
                            ->map(fn ($extension) => trim(strtolower($extension)))
                            ->values()
                        : [],
                    'max_file_size_kb' => $reason->documentType?->max_file_size_kb,
                    'requires_document' => (bool) $reason->requires_document,
                ];
            })
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

        // When the member being separated already holds their own primary membership in
        // another club, the interclub rule lookup must use that membership as the "source"
        // (not the family account being separated from). This way resolveInterclubPackageRule
        // can find rules like: source=Park1/Family → target=Park2/Individual = $3,650.
        $interclubSourceMembership = null;
        if ($hasMultipleClubs) {
            $interclubSourceMembership = Membership::query()
                ->where('status', 'active')
                ->where('is_primary', true)
                ->where('club_id', '!=', $membership->club_id)
                ->whereHas('account.accountMembers', function (Builder $q) use ($accountMember) {
                    $q->where('member_id', $accountMember->member_id)
                      ->where('is_primary_holder', true);
                })
                ->with(['club', 'membershipType'])
                ->first();
        }

        $effectiveSourceClub         = $interclubSourceMembership?->club         ?? $membership->club;
        $effectiveFromMembershipType = $interclubSourceMembership?->membershipType ?? $fromMembershipType;

        $targetMembershipTypes = MembershipType::query()
            ->where('club_id', $membership->club_id)
            ->where('allows_multiple_members', false)
            ->where(function (Builder $q) use ($fromMembershipType, $effectiveFromMembershipType) {
                $q->whereHas('pricingRules', function (Builder $inner) use ($fromMembershipType) {
                    $inner->where('from_membership_type_id', $fromMembershipType->id);
                })->orWhereHas('interclubPackageRulesAsTarget', function (Builder $inner) use ($effectiveFromMembershipType) {
                    $inner->where('source_membership_type_id', $effectiveFromMembershipType->id)
                          ->where('is_active', true);
                });
            })
            ->orderBy('name')
            ->get();

        $effectiveStartDate = $interclubSourceMembership?->start_date ?? $membership->start_date;

        return $targetMembershipTypes
            ->map(function (MembershipType $targetMembershipType) use ($effectiveFromMembershipType, $effectiveSourceClub, $effectiveStartDate, $membership, $age, $hasMultipleClubs) {
                try {
                    $pricing = $this->resolveApplicablePricing(
                        targetClubId: (int) $membership->club_id,
                        membershipType: $targetMembershipType,
                        fromMembershipType: $effectiveFromMembershipType,
                        sourceClub: $effectiveSourceClub,
                        age: $age,
                        hasMultipleClubs: $hasMultipleClubs,
                        sourceMembershipIsActive: true,
                        yearsInSourceClub: $effectiveStartDate
                            ? Carbon::parse($effectiveStartDate)->diffInYears(now())
                            : null
                    );

                    return [
                        'id' => $targetMembershipType->id,
                        'code' => $targetMembershipType->code,
                        'name' => $targetMembershipType->name,
                        'monthly_fee' => (float) $pricing['monthly_fee'],
                        'inscription_fee' => 0.0, // separations are internal transfers, not new enrollments
                        'billing_split_mode' => $pricing['billing_split_mode'] ?? 'single',
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
        // Only counts memberships where the person is the primary holder of that account.
        // Being a dependent/family member on someone else's interclub account does not
        // qualify the person for interclub pricing on their own separate membership.
        return Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', '!=', $currentClubId)
            ->whereHas('account.accountMembers', function (Builder $query) use ($memberId) {
                $query->where('member_id', $memberId)
                      ->where('is_primary_holder', true);
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
            $monthlyFee = $interclubRule->resolveMonthlyFee();

            if ($monthlyFee === null) {
                throw ValidationException::withMessages([
                    'membership_type_id' => 'El paquete interclub aplicable no tiene una cuota capturada para este año. Captúrala en el módulo de Cuotas por año.',
                ]);
            }

            return [
                'monthly_fee' => $monthlyFee,
                'inscription_fee' => (float) ($interclubRule->resolveInscriptionFee() ?? 0),
                'rule_type' => 'interclub',
                'source_membership_becomes_non_billable' => true,
                'billing_split_mode' => $this->isMonthlyPassMembershipType($membershipType) ? 'single' : 'equal_split',
            ];
        }

        $this->ensurePe1PackageEligibility(
            membershipType: $membershipType,
            sourceClub: $sourceClub,
            yearsInSourceClub: $yearsInSourceClub
        );

        $pricingRule = $this->membershipPricingService->resolvePricingRule(
            membershipTypeId: $membershipType->id,
            fromMembershipTypeId: $fromMembershipType?->id,
            age: $this->membershipPricingService->shouldApplyAgeFilter($membershipType) ? $age : null,
            hasMultipleClubs: $hasMultipleClubs
        );

        if (!$pricingRule) {
            throw ValidationException::withMessages([
                'membership_type_id' => 'No se encontró una regla de costo aplicable para la membresía seleccionada.',
            ]);
        }

        $monthlyFee = $pricingRule->resolveMonthlyFee();

        if ($monthlyFee === null) {
            throw ValidationException::withMessages([
                'membership_type_id' => 'La regla de precio encontrada no tiene una cuota capturada para este año. Captúrala en el módulo de Cuotas por año.',
            ]);
        }

        $sourceMembershipBecomesNonBillable = $this->shouldSourceMembershipBecomeNonBillable(
            membershipType: $membershipType,
            fromMembershipType: $fromMembershipType,
            pricingRule: $pricingRule
        );

        return [
            'monthly_fee' => $monthlyFee,
            'inscription_fee' => (float) ($pricingRule->resolveInscriptionFee() ?? 0),
            'rule_type' => 'pricing_rule',
            'source_membership_becomes_non_billable' => $sourceMembershipBecomesNonBillable,
            'billing_split_mode' => $this->resolveBillingSplitMode(
                membershipType: $membershipType,
                sourceMembershipBecomesNonBillable: $sourceMembershipBecomesNonBillable
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
                'source_club_id' => 'El paquete Parque España 1 solo aplica para usuarios provenientes de PE1.',
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
        if ($this->isMonthlyPassMembershipType($membershipType)) {
            return false;
        }

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

    protected function resolveBillingSplitMode(
        MembershipType $membershipType,
        bool $sourceMembershipBecomesNonBillable
    ): string {
        if ($this->isMonthlyPassMembershipType($membershipType)) {
            return 'single';
        }

        return $sourceMembershipBecomesNonBillable ? 'equal_split' : 'single';
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

        // When interclub applies, exhaust ALL interclub rules before falling back
        // to standalone. Without this, a standalone from-type rule (e.g. familiar→individual
        // standalone) would be found before the generic interclub rule, producing the wrong fee.
        if ($hasMultipleClubs) {
            if ($fromMembershipTypeId) {
                $attempts[] = [$fromMembershipTypeId, true];
            }
            $attempts[] = [null, true];
        }

        if ($fromMembershipTypeId) {
            $attempts[] = [$fromMembershipTypeId, false];
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
            ->where('is_active', true)
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
            ->where('requires_multiple_clubs', $requiresMultipleClubs)
            ->where(function (Builder $query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now()->toDateString());
            })
            ->where(function (Builder $query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now()->toDateString());
            });
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
        float $newMonthlyFeeTotal,
        bool $usesSharedBilling
    ): ?float {
        if ($currentMonthlyFee === null || !$usesSharedBilling) {
            return null;
        }

        return round(max($newMonthlyFeeTotal - $currentMonthlyFee, 0), 2);
    }

    protected function resolveCurrentGroupMonthlyFee(Membership $membership): float
    {
        $accountGroupId = $membership->account?->account_group_id;

        if (!$accountGroupId) {
            return (float) $membership->resolved_monthly_fee_total;
        }

        $activePrimaryMemberships = Membership::query()
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('account', function (Builder $query) use ($accountGroupId) {
                $query->where('account_group_id', $accountGroupId);
            })
            ->get();

        if ($activePrimaryMemberships->isEmpty()) {
            return (float) $membership->resolved_monthly_fee_total;
        }

        return (float) $activePrimaryMemberships->max(fn (Membership $activeMembership) => $activeMembership->resolved_monthly_fee_total);
    }

    protected function resolvePreviewMonthlyFeeShare(float $monthlyFeeTotal, string $billingSplitMode): float
    {
        return $billingSplitMode === 'equal_split'
            ? round($monthlyFeeTotal / 2, 2)
            : round($monthlyFeeTotal, 2);
    }

    protected function resolvePreviewAmountDueToday(
        ?float $currentMonthlyFee,
        float $newMonthlyFeeTotal,
        float $newMonthlyFeeShare,
        float $inscriptionFee,
        bool $usesSharedBilling
    ): float {
        if ($currentMonthlyFee === null || !$usesSharedBilling) {
            return round($newMonthlyFeeShare + $inscriptionFee, 2);
        }

        return round(max($newMonthlyFeeTotal - $currentMonthlyFee, 0) + $inscriptionFee, 2);
    }

    protected function buildPricingPreviewExplanation(
        ?float $currentMonthlyFee,
        float $newMonthlyFeeTotal,
        float $newMonthlyFeeShare,
        float $inscriptionFee,
        float $amountDueToday,
        ?float $additionalMonthlyCharge,
        bool $sameClubTransition,
        bool $usesSharedBilling
    ): string {
        $formattedNewMonthlyFeeTotal = number_format($newMonthlyFeeTotal, 2);
        $formattedNewMonthlyFeeShare = number_format($newMonthlyFeeShare, 2);
        $formattedInscriptionFee = number_format($inscriptionFee, 2);
        $formattedAmountDueToday = number_format($amountDueToday, 2);

        if ($currentMonthlyFee === null) {
            $message = $newMonthlyFeeTotal === $newMonthlyFeeShare
                ? "La mensualidad de este parque será de $$formattedNewMonthlyFeeShare."
                : "La cuota total del esquema será de $$formattedNewMonthlyFeeTotal y en este parque se cobrará $$formattedNewMonthlyFeeShare al mes.";

            if ($inscriptionFee > 0) {
                return $message . " Hoy se pagarán $$formattedAmountDueToday considerando mensualidad e inscripción.";
            }

            return $message . " Hoy se pagará $$formattedAmountDueToday.";

            if ($inscriptionFee > 0) {
                return "Se actualizará la cuota mensual a $$formattedNewMonthlyFee y se cobrará un cargo extra de inscripción por $$formattedInscriptionFee.";
            }

            return "Se actualizará la cuota mensual a $$formattedNewMonthlyFee.";
        }

        if (!$usesSharedBilling) {
            $message = $sameClubTransition
                ? "La mensualidad de este parque se actualizará a $" . number_format($newMonthlyFeeShare, 2) . "."
                : "Esta membresía mantendrá un cobro independiente de $" . number_format($newMonthlyFeeShare, 2) . " al mes en este parque.";

            if ($inscriptionFee > 0) {
                return $message . " Hoy se pagarán $" . number_format($amountDueToday, 2) . " considerando mensualidad e inscripción.";
            }

            return $message . " Hoy se pagará $" . number_format($amountDueToday, 2) . ".";
        }

        $formattedCurrentMonthlyFee = number_format($currentMonthlyFee, 2);
        $formattedAdditionalCharge = number_format((float) ($additionalMonthlyCharge ?? 0), 2);
        $difference = round($newMonthlyFeeTotal - $currentMonthlyFee, 2);

        if ($difference > 0) {
            $message = "La cuota total del esquema pasará de $$formattedCurrentMonthlyFee a $$formattedNewMonthlyFeeTotal. En este parque se cobrará $$formattedNewMonthlyFeeShare al mes.";

            if (($additionalMonthlyCharge ?? 0) > 0) {
                $message .= " Hoy se cobrará un ajuste de $$formattedAdditionalCharge";

                if ($inscriptionFee > 0) {
                    $message .= " más $$formattedInscriptionFee de inscripción";
                }

                return $message . ", para un total de $$formattedAmountDueToday.";
            }

            if ($inscriptionFee > 0) {
                return $message . " Hoy solo se cobrará la inscripción por $$formattedInscriptionFee.";
            }

            return $message . " Hoy no se generará cobro adicional.";
        }

        if ($difference < 0) {
            $message = "La cuota total del esquema bajará de $$formattedCurrentMonthlyFee a $$formattedNewMonthlyFeeTotal. En este parque se cobrará $$formattedNewMonthlyFeeShare al mes.";

            if ($inscriptionFee > 0) {
                return $message . " No se generará saldo a favor; hoy solo se cobrará la inscripción por $$formattedInscriptionFee.";
            }

            return $message . " No se generará saldo a favor ni cobro adicional hoy.";
        }

        $message = $sameClubTransition
            ? "La cuota total del esquema se mantiene en $$formattedNewMonthlyFeeTotal."
            : "La cuota total del esquema se mantiene en $$formattedNewMonthlyFeeTotal.";

        $message .= " En este parque se cobrará $$formattedNewMonthlyFeeShare al mes.";

        if ($inscriptionFee > 0) {
            return $message . " Hoy solo se cobrará la inscripción por $$formattedInscriptionFee.";
        }

        return $message . " Hoy no se generará cobro adicional.";

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
        ?MembershipAccountGroup $accountGroup = null,
        ?int $originAccountId = null,
        ?string $separationReason = null,
        ?string $internalAccountNumber = null,
    ): MembershipAccount {
        $group = $accountGroup ?? $this->createAccountGroup();

        return MembershipAccount::create([
            'account_group_id'        => $group->id,
            'club_id'                 => $club->id,
            'membership_number'       => $this->generateMembershipNumber($club),
            'internal_account_number' => $internalAccountNumber,
            'account_type'            => $accountType,
            'status'                  => $status,
            'origin_account_id'       => $originAccountId,
            'separation_reason'       => $separationReason,
        ]);
    }

    protected function buildAccountTree(MembershipAccount $account): ?array
    {
        $formatAccount = function (MembershipAccount $acc) {
            $primary = $acc->memberships->first();
            $holder  = $acc->primaryHolder?->member;
            return [
                'id'                   => $acc->id,
                'membership_id'        => $primary?->id,
                'membership_number'    => $acc->membership_number,
                'holder_name'          => trim(collect([
                    $holder?->first_name,
                    $holder?->last_name,
                    $holder?->second_last_name,
                ])->filter()->implode(' ')),
                'membership_type_name' => $primary?->membershipType?->name,
                'status'               => $acc->status,
                'separation_reason'    => $acc->separation_reason,
            ];
        };

        $origin  = $account->originAccount ? $formatAccount($account->originAccount) : null;
        $derived = $account->derivedAccounts
            ->map(fn ($d) => $this->buildDerivedNode($d, $formatAccount))
            ->values()
            ->all();

        if (!$origin && empty($derived)) {
            return null;
        }

        return [
            'origin'  => $origin,
            'derived' => $derived,
        ];
    }

    private function buildDerivedNode(MembershipAccount $account, callable $formatAccount): array
    {
        $account->loadMissing([
            'primaryHolder.member',
            'memberships' => fn ($q) => $q->where('is_primary', true),
            'derivedAccounts.primaryHolder.member',
            'derivedAccounts.memberships' => fn ($q) => $q->where('is_primary', true),
        ]);

        $node = $formatAccount($account);
        $node['derived'] = $account->derivedAccounts
            ->map(fn ($child) => $this->buildDerivedNode($child, $formatAccount))
            ->values()
            ->all();

        return $node;
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
