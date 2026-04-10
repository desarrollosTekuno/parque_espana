<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Catalogs\MaritalStatus;
use App\Models\Catalogs\Nationality;
use App\Models\Catalogs\Relationship;
use App\Models\Members\Address;
use App\Models\Members\EmploymentInfo;
use App\Models\Members\Member;
use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
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
    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $prefix = 'members';
            $driver = DB::getDriverName();

            $query = MembershipAccount::query()
                ->with([
                    'primaryHolder.member',
                    'memberships' => fn ($membershipQuery) => $membershipQuery
                        ->with(['membershipType', 'club'])
                        ->where('status', 'active')
                        ->where('is_primary', true),
                ])
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
                    $fullName = trim(collect([
                        $holder?->first_name,
                        $holder?->last_name,
                        $holder?->second_last_name,
                    ])->filter()->implode(' '));

                    return [
                        'id' => $account->id,
                        'membership_id' => $billableMembership?->id ?? $activeMemberships->first()?->id,
                        'membership_number' => $account->membership_number,
                        'holder_name' => $fullName,
                        'email' => $holder?->email,
                        'phone' => $holder?->phone,
                        'monthly_fee' => (float) ($billableMembership?->monthly_fee ?? 0),
                        'status' => $billableMembership?->status ?? $activeMemberships->first()?->status,
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

        $relationships = Relationship::select('id', 'name')->get();
        $nationalities = Nationality::select('id', 'code', 'name', 'demonym')
            ->orderBy('name')
            ->get();
        $maritalStatuses = MaritalStatus::select('id', 'code', 'name')
            ->orderBy('name')
            ->get();
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

        return Inertia::render('Members/Create', compact(
            'membershipTypes',
            'originMembershipTypes',
            'clubs',
            'relationships',
            'nationalities',
            'maritalStatuses'
        ));
    }

    public function createAdditionalMembership(Request $request, Membership $membership)
    {
        $clubId = session('club_id');

        if ((int) $membership->club_id !== (int) $clubId) {
            abort(404);
        }

        $membership->load([
            'account.primaryHolder.member.primaryAddress',
            'account.primaryHolder.member.employmentInfo',
            'account.accountMembers.relationship',
            'account.accountMembers.member.primaryAddress',
            'account.accountMembers.member.employmentInfo',
            'membershipType',
            'club',
            'account.memberships.membershipType',
            'account.memberships.club',
        ]);

        if ($membership->status !== 'active') {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'Solo puedes generar una solicitud para el otro parque a partir de una membresia activa.',
                'exception' => '',
            ]);
        }

        $targetClub = Club::query()
            ->where('id', '!=', $membership->club_id)
            ->orderBy('name')
            ->first();

        if (!$targetClub) {
            return redirect()->route('members.index')->withErrors([
                'messageError' => 'No se encontro un parque destino disponible para esta solicitud.',
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

        $sourceMember = $membership->account?->primaryHolder?->member;

        $relationships = Relationship::select('id', 'name')->get();
        $nationalities = Nationality::select('id', 'code', 'name', 'demonym')
            ->orderBy('name')
            ->get();
        $maritalStatuses = MaritalStatus::select('id', 'code', 'name')
            ->orderBy('name')
            ->get();

        $prefillMembers = $membership->account->accountMembers
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
                    'birth_place' => $member?->birth_place,
                    'city' => $member?->city,
                    'state' => $member?->state,
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
                        'city' => $address?->city,
                        'state' => $address?->state,
                        'country' => $address?->country,
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

        return Inertia::render('Members/Create', [
            'membershipTypes' => $targetMembershipTypes,
            'originMembershipTypes' => collect(),
            'clubs' => collect([$targetClub]),
            'relationships' => $relationships,
            'nationalities' => $nationalities,
            'maritalStatuses' => $maritalStatuses,
            'isCrossClubRequest' => true,
            'targetClub' => $targetClub,
            'sourceMembership' => [
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
            ],
            'prefillMembers' => $prefillMembers,
        ]);
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
                'members.*.city' => ['nullable', 'string', 'max:255'],
                'members.*.state' => ['nullable', 'string', 'max:255'],
                'members.*.nationality_id' => ['nullable', new ExistsInSchema('catalogs', 'nationalities', 'id')],
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

        if (!empty($validated['source_membership_id'])) {
            $sourceMembership = Membership::query()
                ->with(['membershipType', 'club', 'account.primaryHolder.member'])
                ->findOrFail($validated['source_membership_id']);

            $fromMembershipType = $sourceMembership->membershipType;
            $sourceClub = $sourceMembership->club;
            $clubId = $validated['target_club_id'] ?? $clubId;
            $hasMultipleClubs = true;
            $sourceMembershipIsActive = $sourceMembership->status === 'active';
            $yearsInSourceClub = $sourceMembership->start_date
                ? Carbon::parse($sourceMembership->start_date)->diffInYears(now())
                : null;

            if ((int) $clubId === (int) $sourceMembership->club_id) {
                return redirect()->back()->withErrors([
                    'messageError' => 'La solicitud para el otro parque debe apuntar a un club destino distinto al de origen.',
                    'exception' => '',
                ]);
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
                'membership_type_id' => 'La membresia seleccionada no pertenece al club actual.',
            ]);
        }

        if ($sourceClub && $fromMembershipType && $fromMembershipType->club_id !== $sourceClub->id) {
            return redirect()->back()->withErrors([
                'messageError' => 'La membresia de origen no pertenece al club de origen seleccionado.',
                'exception' => '',
            ]);
        }

        if ($sourceMembership && !$sourceMembershipIsActive) {
            return redirect()->back()->withErrors([
                'messageError' => 'La membresia de origen debe estar activa para generar una solicitud en el otro parque.',
                'exception' => '',
            ]);
        }

        if ($sourceMembership) {
            $sourcePrimaryHolderId = $sourceMembership->account?->primaryHolder?->member_id;
            $requestedPrimaryHolderId = collect($validated['members'])
                ->firstWhere('is_primary_holder', true)['id'] ?? null;

            if ($sourcePrimaryHolderId && (int) $requestedPrimaryHolderId !== (int) $sourcePrimaryHolderId) {
                return redirect()->back()->withErrors([
                    'messageError' => 'El titular de la nueva solicitud debe coincidir con el titular de la membresia origen.',
                    'exception' => '',
                ]);
            }

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

        $primaryMembers = collect($validated['members'])
            ->where('is_primary_holder', true)
            ->values();

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
                'messageError' => 'La membresia seleccionada no permite multiples integrantes.',
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
                'messageError' => 'La membresia seleccionada requiere una membresia familiar de origen.',
                'exception' => '',
            ]);
        }

        if ($membershipType->requires_origin_family && !$fromMembershipType->allows_multiple_members) {
            // throw ValidationException::withMessages([
            //     'from_membership_type_id' => 'La membresia seleccionada debe provenir de una membresia familiar.',
            // ]);
            return redirect()->back()->withErrors([
                'messageError' => 'La membresia seleccionada debe provenir de una membresia familiar.',
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

            DB::transaction(function () use ($validated, $membershipType, $pricing, $clubId, $club, $fromMembershipType, $sourceMembership) {
                $membershipAccount = MembershipAccount::create([
                    'membership_number' => $this->generateMembershipNumber($club),
                    'account_type' => $membershipType->allows_multiple_members ? 'family' : 'individual',
                    'status' => 'pending',
                ]);

                foreach ($validated['members'] as $memberData) {
                    $memberAttributes = [
                        'first_name' => $memberData['first_name'],
                        'last_name' => $memberData['last_name'],
                        'second_last_name' => $memberData['second_last_name'] ?? null,
                        'birthdate' => $memberData['birthdate'] ?? null,
                        'birth_place' => $memberData['birth_place'] ?? null,
                        'state' => $memberData['state'] ?? null,
                        'city' => $memberData['city'] ?? null,
                        'nationality_id' => $memberData['nationality_id'] ?? null,
                        'marital_status_id' => $memberData['marital_status_id'] ?? null,
                        'phone' => $memberData['phone'] ?? null,
                        'email' => $memberData['email'] ?? null,
                        'occupation' => $memberData['occupation'] ?? null,
                        'school_name' => $memberData['school_name'] ?? null,
                    ];

                    $member = !empty($memberData['id'])
                        ? tap(Member::findOrFail($memberData['id']))->update($memberAttributes)
                        : Member::create($memberAttributes);

                    if ($this->hasFilledValues($memberData['address'] ?? [])) {
                        Address::updateOrCreate([
                            'member_id' => $member->id,
                            'is_primary' => true,
                        ], [
                            'street' => $memberData['address']['street'] ?? null,
                            'neighborhood' => $memberData['address']['neighborhood'] ?? null,
                            'postal_code' => $memberData['address']['postal_code'] ?? null,
                            'city' => $memberData['address']['city'] ?? null,
                            'state' => $memberData['address']['state'] ?? null,
                            'country' => $memberData['address']['country'] ?? null,
                            'years_in_city' => $memberData['address']['years_in_city'] ?? null,
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

                    MembershipAccountMember::create([
                        'membership_account_id' => $membershipAccount->id,
                        'member_id' => $member->id,
                        'relationship_id' => $memberData['relationship_id'] ?? null,
                        'is_primary_holder' => $memberData['is_primary_holder'],
                    ]);
                }

                Membership::create([
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

                if ($sourceMembership && ($pricing['source_membership_becomes_non_billable'] ?? false)) {
                    $sourceMembership->update([
                        'is_billable' => false,
                    ]);
                }
            });

            return redirect()
                ->back()
                ->with('success', 'La cuenta de membresía y sus integrantes se registraron correctamente.');
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
            'source_membership_becomes_non_billable' => (bool) $pricingRule->requires_multiple_clubs
                || $this->isPe1PackageMembershipType($membershipType),
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
                fn (Builder $query) => $query->where('requires_active_source_membership', false)
            )
            ->when(
                $yearsInSourceClub !== null,
                fn (Builder $query) => $query->where(function (Builder $yearsQuery) use ($yearsInSourceClub) {
                    $yearsQuery->whereNull('min_years_in_source_club')
                        ->orWhere('min_years_in_source_club', '<=', $yearsInSourceClub);
                }),
                fn (Builder $query) => $query->whereNull('min_years_in_source_club')
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
                fn (Builder $query) => $query->where('from_membership_type_id', $fromMembershipTypeId),
                fn (Builder $query) => $query->whereNull('from_membership_type_id')
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
                fn (Builder $query) => $query->whereNull('min_age')->whereNull('max_age')
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

    protected function validationExceptionResponse(ValidationException $e)
    {
        $errors = $e->errors();
        $firstMessage = collect($errors)->flatten()->first() ?? 'Ocurrio un error de validacion.';

        return redirect()->back()->withErrors(array_merge($errors, [
            'messageError' => $firstMessage,
            'exception' => '',
        ]));
    }
}
