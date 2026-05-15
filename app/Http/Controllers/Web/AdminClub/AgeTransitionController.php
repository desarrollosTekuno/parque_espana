<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Catalogs\Country;
use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\MaritalStatus;
use App\Models\Catalogs\Relationship;
use App\Models\Members\Address;
use App\Models\Members\EmploymentInfo;
use App\Models\Members\MemberDocument;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\PendingAgeTransition;
use App\Rules\ExistsInSchema;
use App\Services\Billing\MembershipChargeService;
use App\Services\Billing\MembershipPricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AgeTransitionController extends Controller
{
    public function __construct(
        protected MembershipChargeService $chargeService,
        protected MembershipPricingService $pricingService
    ) {
    }

    // ─── Index ───────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $clubId = session('club_id');
            $prefix = 'age_transitions';
            $driver = DB::getDriverName();

            $query = PendingAgeTransition::query()
                ->with([
                    'member',
                    'targetMembershipType',
                    'membership.club',
                    'membership.membershipType',
                    'promotedBy',
                    'dismissedBy',
                ])
                ->whereHas('membership', fn (Builder $q) => $q->where('club_id', $clubId));

            $statusFilter = $request->input("{$prefix}_status", 'pending');
            if ($statusFilter && $statusFilter !== 'all') {
                $query->where('status', $statusFilter);
            }

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';
                $query->whereHas('member', fn (Builder $q) =>
                    $q->where('first_name', $like, "%{$search}%")
                      ->orWhere('last_name', $like, "%{$search}%")
                      ->orWhere('second_last_name', $like, "%{$search}%")
                );
            }

            if ($transitionType = $request->input("{$prefix}_transition_type")) {
                $query->where('transition_type', $transitionType);
            }

            $transitions = $query
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('identified_at', 'desc')
                ->paginate(
                    $request->input("{$prefix}_per_page", 15),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(fn (PendingAgeTransition $t) => $this->formatTransition($t))
                ->appends($request->all());

            return Inertia::render('Members/AgeTransitions', [
                'transitions' => $transitions,
                'filters' => [
                    'search'          => $request->input("{$prefix}_search"),
                    'status'          => $statusFilter,
                    'transition_type' => $request->input("{$prefix}_transition_type"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('Members/AgeTransitions', [
                'transitions'  => ['data' => [], 'total' => 0],
                'filters'      => [],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    // ─── Promote: form page ──────────────────────────────────────────────────

    public function createPromote(PendingAgeTransition $ageTransition)
    {
        $clubId = session('club_id');

        // Lazy-load membership to check club
        if ((int) $ageTransition->membership->club_id !== (int) $clubId) {
            abort(404);
        }

        if ($ageTransition->status !== 'pending') {
            return redirect()
                ->route('members.age-transitions.index')
                ->withErrors(['messageError' => 'Esta transición ya fue procesada.']);
        }

        $ageTransition->load([
            'membership.club',
            'membership.membershipType',
            'member.primaryAddress.country',
            'member.primaryAddress.state',
            'member.primaryAddress.city',
            'member.employmentInfo',
            'member.birthCountry',
            'member.birthState',
            'member.birthCity',
            'member.nationality',
            'member.maritalStatus',
            'member.documents' => fn ($q) => $q->orderByDesc('created_at'),
            'targetMembershipType.documentTypes.relationships',
        ]);

        $member     = $ageTransition->member;
        $address    = $member->primaryAddress;
        $employment = $member->employmentInfo;

        $titularRelationshipId = Relationship::query()->where('name', 'Titular')->value('id') ?? 1;

        $prefillMember = [
            'id'                  => $member->id,
            'first_name'          => $member->first_name,
            'last_name'           => $member->last_name,
            'second_last_name'    => $member->second_last_name,
            'birthdate'           => $member->birthdate,
            'birth_place'         => $member->birth_place,
            'birth_country_id'    => $member->birth_country_id,
            'birth_state_id'      => $member->birth_state_id,
            'birth_city_id'       => $member->birth_city_id,
            'state'               => $member->birthState?->name ?? $member->state,
            'city'                => $member->birthCity?->name ?? $member->city,
            'nationality_id'      => $member->nationality_id,
            'marital_status_id'   => $member->marital_status_id,
            'phone'               => $member->phone,
            'email'               => $member->email,
            'occupation'          => $member->occupation,
            'school_name'         => $member->school_name,
            'is_primary_holder'   => true,
            'relationship_id'     => $titularRelationshipId,
            'relationship_name'   => 'Titular',
            'address' => [
                'street'       => $address?->street,
                'neighborhood' => $address?->neighborhood,
                'postal_code'  => $address?->postal_code,
                'country_id'   => $address?->country_id,
                'state_id'     => $address?->state_id,
                'city_id'      => $address?->city_id,
                'years_in_city' => $address?->years_in_city,
            ],
            'employment' => [
                'company_name'    => $employment?->company_name,
                'company_address' => $employment?->company_address,
                'company_phone'   => $employment?->company_phone,
            ],
            'existing_documents' => $member->documents
                ->sortByDesc('created_at')
                ->map(fn ($doc) => [
                    'id'               => $doc->id,
                    'document_type_id' => $doc->document_type_id,
                    'uploaded_at'      => $doc->created_at?->toDateString(),
                ])
                ->unique('document_type_id')
                ->values(),
        ];

        $targetType = $ageTransition->targetMembershipType;
        $targetTypePayload = [
            'id'   => $targetType->id,
            'name' => $targetType->name,
            'code' => $targetType->code,
            'document_types' => $targetType->documentTypes->map(fn ($dt) => [
                'id'                  => $dt->id,
                'name'                => $dt->name,
                'allowed_extensions'  => $dt->allowed_extensions,
                'pivot' => [
                    'is_required'   => (bool) $dt->pivot->is_required,
                    'allow_multiple' => (bool) $dt->pivot->allow_multiple,
                    'number_files'  => (int) $dt->pivot->number_files,
                ],
                'relationships' => $dt->relationships->map(fn ($r) => [
                    'id'   => $r->id,
                    'name' => $r->name,
                ])->values(),
            ])->values(),
        ];

        $countries = Country::select('id', 'iso2 as code', 'name', 'translations', 'demonym')
            ->orderBy('name')
            ->get();

        return Inertia::render('Members/AgeTransitionPromote', [
            'transition' => [
                'id'                   => $ageTransition->id,
                'transition_type'      => $ageTransition->transition_type,
                'monthly_fee'          => (float) $ageTransition->monthly_fee,
                'has_multiple_clubs'   => (bool) $ageTransition->has_multiple_clubs,
                'from_membership_type' => $ageTransition->membership->membershipType?->name,
                'club_code'            => $ageTransition->membership->club?->code,
                'club_name'            => $ageTransition->membership->club?->name,
                'target_membership_type' => $targetTypePayload,
            ],
            'prefillMember'  => $prefillMember,
            'countries'      => $countries,
            'nationalities'  => $countries,
            'maritalStatuses' => MaritalStatus::select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    // ─── Promote: form submit ────────────────────────────────────────────────

    public function promote(Request $request, PendingAgeTransition $ageTransition)
    {
        try {
            $clubId = session('club_id');

            if ((int) $ageTransition->membership->club_id !== (int) $clubId) {
                abort(404);
            }

            if ($ageTransition->status !== 'pending') {
                return redirect()->back()->withErrors([
                    'messageError' => 'Esta transición ya fue procesada.',
                    'exception'    => '',
                ]);
            }

            $validated = $request->validate([
                'member.first_name'                 => ['required', 'string', 'max:255'],
                'member.last_name'                  => ['required', 'string', 'max:255'],
                'member.second_last_name'           => ['nullable', 'string', 'max:255'],
                'member.birthdate'                  => ['nullable', 'date'],
                'member.birth_place'                => ['nullable', 'string', 'max:255'],
                'member.birth_country_id'           => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'member.birth_state_id'             => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'member.birth_city_id'              => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'member.nationality_id'             => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'member.marital_status_id'          => ['nullable', new ExistsInSchema('catalogs', 'marital_statuses', 'id')],
                'member.phone'                      => ['nullable', 'string', 'max:50'],
                'member.email'                      => ['nullable', 'email', 'max:255'],
                'member.occupation'                 => ['nullable', 'string', 'max:255'],
                'member.school_name'                => ['nullable', 'string', 'max:255'],
                'member.address.street'             => ['nullable', 'string', 'max:255'],
                'member.address.neighborhood'       => ['nullable', 'string', 'max:255'],
                'member.address.postal_code'        => ['nullable', 'string', 'max:10'],
                'member.address.country_id'         => ['nullable', new ExistsInSchema('catalogs', 'countries', 'id')],
                'member.address.state_id'           => ['nullable', new ExistsInSchema('catalogs', 'states', 'id')],
                'member.address.city_id'            => ['nullable', new ExistsInSchema('catalogs', 'cities', 'id')],
                'member.address.years_in_city'      => ['nullable', 'integer', 'min:0', 'max:999'],
                'member.employment.company_name'    => ['nullable', 'string', 'max:255'],
                'member.employment.company_address' => ['nullable', 'string', 'max:255'],
                'member.employment.company_phone'   => ['nullable', 'string', 'max:50'],
                'documents'                         => ['nullable', 'array'],
                'documents.*.document_type_id'      => ['required_with:documents.*', 'integer'],
                'documents.*.files'                 => ['nullable', 'array'],
                'documents.*.files.*'               => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $memberData   = $validated['member'] ?? [];
            $documentsRaw = $validated['documents'] ?? [];

            $ageTransition->load([
                'membership.club',
                'membership.membershipType',
                'membership.account.accountGroup',
                'membership.account.accountMembers.member',
                'member',
                'targetMembershipType',
            ]);

            $savedMemberDocuments = [];

            DB::transaction(function () use ($ageTransition, $memberData, $documentsRaw, &$savedMemberDocuments) {
                $member = $ageTransition->member;

                // 1. Update member personal data
                $member->update(array_filter([
                    'first_name'       => $memberData['first_name'] ?? null,
                    'last_name'        => $memberData['last_name'] ?? null,
                    'second_last_name' => $memberData['second_last_name'] ?? null,
                    'birthdate'        => $memberData['birthdate'] ?? null,
                    'birth_place'      => $memberData['birth_place'] ?? null,
                    'birth_country_id' => $memberData['birth_country_id'] ?? null,
                    'birth_state_id'   => $memberData['birth_state_id'] ?? null,
                    'birth_city_id'    => $memberData['birth_city_id'] ?? null,
                    'nationality_id'   => $memberData['nationality_id'] ?? null,
                    'marital_status_id' => $memberData['marital_status_id'] ?? null,
                    'phone'            => $memberData['phone'] ?? null,
                    'email'            => $memberData['email'] ?? null,
                    'occupation'       => $memberData['occupation'] ?? null,
                    'school_name'      => $memberData['school_name'] ?? null,
                ], fn ($v) => $v !== null));

                // 2. Update address
                $addressData = array_filter($memberData['address'] ?? [], fn ($v) => $v !== null && $v !== '');
                if (!empty($addressData)) {
                    Address::updateOrCreate(
                        ['member_id' => $member->id, 'is_primary' => true],
                        $addressData
                    );
                }

                // 3. Update employment
                $employmentData = array_filter($memberData['employment'] ?? [], fn ($v) => $v !== null && $v !== '');
                if (!empty($employmentData)) {
                    EmploymentInfo::updateOrCreate(
                        ['member_id' => $member->id],
                        $employmentData
                    );
                }

                // 4. Queue documents for upload after transaction
                if (!empty($documentsRaw)) {
                    $savedMemberDocuments[$member->id] = $documentsRaw;
                }

                // 5. Execute transition
                match ($ageTransition->transition_type) {
                    'family_to_solidaria'    => $this->executeFamilyToSolidaria($ageTransition),
                    'solidaria_to_individual' => $this->executeSolidariaToIndividual($ageTransition),
                    default => throw new \RuntimeException('Tipo de transición desconocido.'),
                };
            });

            // 6. Upload documents outside the transaction
            $this->uploadTransitionDocuments($savedMemberDocuments);

            return redirect()
                ->route('members.age-transitions.index')
                ->with('success', 'Transición promovida correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al promover la transición: ' . $e->getMessage(),
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    // ─── Dismiss ────────────────────────────────────────────────────────────

    public function dismiss(Request $request, PendingAgeTransition $ageTransition)
    {
        try {
            $clubId = session('club_id');

            if ((int) $ageTransition->membership->club_id !== (int) $clubId) {
                abort(404);
            }

            if ($ageTransition->status !== 'pending') {
                return redirect()->back()->withErrors([
                    'messageError' => 'Esta transición ya fue procesada.',
                    'exception'    => '',
                ]);
            }

            $validated = $request->validate([
                'dismissal_reason' => ['nullable', 'string', 'max:255'],
            ]);

            $ageTransition->update([
                'status'           => 'dismissed',
                'dismissed_at'     => now(),
                'dismissed_by'     => auth()->id(),
                'dismissal_reason' => $validated['dismissal_reason'] ?? null,
            ]);

            return redirect()->back()->with('success', 'Transición descartada.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al descartar la transición.',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    // ─── Transition execution (called inside a DB::transaction) ─────────────

    private function executeFamilyToSolidaria(PendingAgeTransition $transition): void
    {
        $familyMembership = $transition->membership;
        $member           = $transition->member;
        $targetType       = $transition->targetMembershipType;

        $accountMember = $familyMembership->account->accountMembers
            ->firstWhere('member_id', $member->id);

        if (!$accountMember) {
            throw new \RuntimeException(
                "El integrante {$member->id} ya no pertenece a la cuenta familiar."
            );
        }

        if ($accountMember->is_primary_holder) {
            throw new \RuntimeException('El titular principal no puede ser promovido con este flujo.');
        }

        $titularRelationshipId = Relationship::query()->where('name', 'Titular')->value('id');

        // Re-evaluar en tiempo real si ya tiene membresía propia activa en otro parque.
        // El dato guardado en la transición puede estar desactualizado si el integrante
        // fue promovido en otro parque entre la detección y la aprobación.
        $existingAccountGroup      = $this->findExistingAccountGroup($member->id, $familyMembership->club_id);
        $currentlyHasMultipleClubs = $existingAccountGroup !== null;
        $billingSplitMode          = $currentlyHasMultipleClubs ? 'equal_split' : 'single';

        // Recalcular la cuota si el estado multiclub cambió respecto a lo detectado originalmente
        $monthlyFeeTotal = $this->resolveCurrentMonthlyFee(
            transition:                $transition,
            fromMembershipTypeId:      $familyMembership->membership_type_id,
            targetMembershipTypeId:    $targetType->id,
            member:                    $member,
            currentlyHasMultipleClubs: $currentlyHasMultipleClubs,
        );

        $group = $existingAccountGroup ?? MembershipAccountGroup::create(['status' => 'active']);

        $newAccount = \App\Models\Memberships\MembershipAccount::create([
            'account_group_id'  => $group->id,
            'club_id'           => $familyMembership->club_id,
            'membership_number' => $this->generateMembershipNumber($familyMembership->club),
            'account_type'      => 'individual',
            'status'            => 'active',
            'origin_account_id' => $familyMembership->membership_account_id,
            'separation_reason' => 'Promoción por edad',
        ]);

        MembershipAccountMember::create([
            'membership_account_id' => $newAccount->id,
            'member_id'             => $member->id,
            'relationship_id'       => $titularRelationshipId ?: $accountMember->relationship_id,
            'is_primary_holder'     => true,
        ]);

        $newMembership = Membership::create([
            'membership_account_id'     => $newAccount->id,
            'club_id'                   => $familyMembership->club_id,
            'membership_type_id'        => $targetType->id,
            'origin_membership_type_id' => $familyMembership->membership_type_id,
            'is_primary'                => true,
            'is_billable'               => true,
            'monthly_fee'               => $monthlyFeeTotal,
            'monthly_fee_total'         => $monthlyFeeTotal,
            'monthly_fee_share'         => $monthlyFeeTotal,
            'billing_split_mode'        => $billingSplitMode,
            'start_date'                => now()->toDateString(),
            'end_date'                  => $targetType->validity_months
                ? now()->addMonthsNoOverflow($targetType->validity_months)->toDateString()
                : null,
            'status'                    => 'active',
        ]);

        // CRÍTICO: cargar account para que synchronizeMembershipFees pueda encontrar
        // el account_group_id y distribuir la cuota entre los parques del grupo.
        // Sin esto, el servicio trata la membresía como standalone y fuerza billing_split_mode='single'.
        $newMembership->load('account');

        $newMembership = $this->chargeService
            ->synchronizeMembershipFees($newMembership, $monthlyFeeTotal, null, $billingSplitMode, 'Promoción por edad — ' . $targetType->name)
            ->firstWhere('id', $newMembership->id)
            ?? $newMembership->fresh(['membershipType', 'account.primaryHolder']);

        $this->chargeService->createInitialCharges(
            membership: $newMembership,
            monthlyFee: $monthlyFeeTotal,
            inscriptionFee: 0.0,
            metadata: [
                'charge_origin'             => 'age_transition',
                'source_membership_id'      => $familyMembership->id,
                'pending_age_transition_id' => $transition->id,
                'has_multiple_clubs'        => $currentlyHasMultipleClubs,
                'billing_split_mode'        => $billingSplitMode,
            ],
            chargeDate: now()
        );

        $accountMember->delete();

        DB::table('memberships.membership_history')->insert([
            'membership_id'          => $newMembership->id,
            'old_membership_type_id' => $familyMembership->membership_type_id,
            'new_membership_type_id' => $targetType->id,
            'changed_by'             => auth()->id(),
            'effective_date'         => now()->toDateString(),
            'reason'                 => 'Promoción por edad desde cuenta familiar',
            'previous_monthly_fee'   => null,
            'new_monthly_fee'        => $monthlyFeeTotal,
            'metadata'               => json_encode([
                'transition_kind'              => 'age_transition_family_to_solidaria',
                'source_membership_id'         => $familyMembership->id,
                'pending_age_transition_id'    => $transition->id,
                'has_multiple_clubs'           => $currentlyHasMultipleClubs,
                'stored_has_multiple_clubs'    => $transition->has_multiple_clubs,
                'billing_split_mode'           => $billingSplitMode,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transition->update([
            'status'      => 'promoted',
            'promoted_at' => now(),
            'promoted_by' => auth()->id(),
        ]);
    }

    private function executeSolidariaToIndividual(PendingAgeTransition $transition): void
    {
        $solidariaMembership = $transition->membership;
        $member              = $transition->member;
        $targetType          = $transition->targetMembershipType;
        $previousTypeId      = $solidariaMembership->membership_type_id;
        $previousFee         = (float) $solidariaMembership->monthly_fee;

        // Re-evaluar en tiempo real si ya tiene membresía propia activa en otro parque
        $existingAccountGroup      = $this->findExistingAccountGroup($member->id, $solidariaMembership->club_id);
        $currentlyHasMultipleClubs = $existingAccountGroup !== null;
        $billingSplitMode          = $currentlyHasMultipleClubs ? 'equal_split' : 'single';

        // Si ahora tiene múltiples parques pero la cuenta aún no está enlazada al grupo,
        // enlazarla para que synchronizeMembershipFees distribuya correctamente los cargos
        $solidariaMembership->loadMissing('account');
        if ($currentlyHasMultipleClubs && $solidariaMembership->account && !$solidariaMembership->account->account_group_id) {
            $solidariaMembership->account->update(['account_group_id' => $existingAccountGroup->id]);
            $solidariaMembership->account->refresh();
        }

        // Recalcular la cuota si el estado multiclub cambió respecto a lo detectado originalmente
        $monthlyFeeTotal = $this->resolveCurrentMonthlyFee(
            transition:                $transition,
            fromMembershipTypeId:      $previousTypeId,
            targetMembershipTypeId:    $targetType->id,
            member:                    $member,
            currentlyHasMultipleClubs: $currentlyHasMultipleClubs,
        );

        $solidariaMembership->update([
            'membership_type_id'        => $targetType->id,
            'origin_membership_type_id' => $previousTypeId,
            'monthly_fee'               => $monthlyFeeTotal,
            'monthly_fee_total'         => $monthlyFeeTotal,
            'monthly_fee_share'         => $monthlyFeeTotal,
            'billing_split_mode'        => $billingSplitMode,
            'start_date'                => now()->toDateString(),
            'end_date'                  => $targetType->validity_months
                ? now()->addMonthsNoOverflow($targetType->validity_months)->toDateString()
                : null,
        ]);

        DB::table('memberships.membership_history')->insert([
            'membership_id'          => $solidariaMembership->id,
            'old_membership_type_id' => $previousTypeId,
            'new_membership_type_id' => $targetType->id,
            'changed_by'             => auth()->id(),
            'effective_date'         => now()->toDateString(),
            'reason'                 => 'Promoción por edad de solidaria a individual',
            'previous_monthly_fee'   => $previousFee,
            'new_monthly_fee'        => $monthlyFeeTotal,
            'metadata'               => json_encode([
                'transition_kind'           => 'age_transition_solidaria_to_individual',
                'pending_age_transition_id' => $transition->id,
                'has_multiple_clubs'        => $currentlyHasMultipleClubs,
                'stored_has_multiple_clubs' => $transition->has_multiple_clubs,
                'billing_split_mode'        => $billingSplitMode,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $solidariaMembership = $this->chargeService
            ->synchronizeMembershipFees($solidariaMembership, $monthlyFeeTotal, null, $billingSplitMode, 'Promoción por edad — ' . $targetType->name)
            ->firstWhere('id', $solidariaMembership->id)
            ?? $solidariaMembership->fresh(['membershipType', 'account.primaryHolder']);

        $this->chargeService->createInitialCharges(
            membership: $solidariaMembership,
            monthlyFee: $monthlyFeeTotal,
            inscriptionFee: 0.0,
            metadata: [
                'charge_origin'               => 'age_transition',
                'previous_membership_type_id' => $previousTypeId,
                'pending_age_transition_id'   => $transition->id,
                'has_multiple_clubs'          => $currentlyHasMultipleClubs,
                'billing_split_mode'          => $billingSplitMode,
            ],
            chargeDate: now(),
            reconcileExistingMonthlyCharge: true
        );

        $transition->update([
            'status'      => 'promoted',
            'promoted_at' => now(),
            'promoted_by' => auth()->id(),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Determina la cuota mensual correcta en tiempo real.
     *
     * Si el estado multiclub cambió desde que se detectó la transición
     * (p.ej. el integrante fue promovido en otro parque antes de que se
     * aprobara esta transición), consulta el servicio de pricing para obtener
     * la regla vigente que corresponda al estado actual (requires_multiple_clubs),
     * respetando is_active, valid_from, valid_until y el orden de fallback correcto.
     *
     * Esto garantiza que:
     *  - Parque 1 promovido primero → precio single correcto
     *  - Parque 2 promovido después → precio multiclub + split 50/50
     */
    private function resolveCurrentMonthlyFee(
        PendingAgeTransition $transition,
        int $fromMembershipTypeId,
        int $targetMembershipTypeId,
        \App\Models\Members\Member $member,
        bool $currentlyHasMultipleClubs,
    ): float {
        // Si el estado no cambió, usamos la cuota guardada (evita una query extra)
        if ((bool) $transition->has_multiple_clubs === $currentlyHasMultipleClubs) {
            return (float) $transition->monthly_fee;
        }

        $age = $member->birthdate
            ? (int) \Carbon\Carbon::parse($member->birthdate)->diffInYears(now())
            : null;

        // Delegar al servicio de pricing: aplica is_active, valid_from, valid_until
        // y el orden de fallback correcto: [fromId, multiclub] → [null, multiclub] → [fromId, false] → [null, false]
        $rule = $this->pricingService->resolvePricingRule(
            membershipTypeId:     $targetMembershipTypeId,
            fromMembershipTypeId: $fromMembershipTypeId,
            age:                  $age,
            hasMultipleClubs:     $currentlyHasMultipleClubs,
        );

        if ($rule) {
            Log::info('Age transition: fee recalculated due to multiclub state change', [
                'transition_id'              => $transition->id,
                'stored_has_multiple_clubs'  => $transition->has_multiple_clubs,
                'current_has_multiple_clubs' => $currentlyHasMultipleClubs,
                'stored_monthly_fee'         => $transition->monthly_fee,
                'resolved_monthly_fee'       => $rule->monthly_fee,
                'pricing_rule_id'            => $rule->id,
            ]);

            return (float) $rule->monthly_fee;
        }

        // Fallback: usar la cuota guardada y loggear la advertencia
        Log::warning('Age transition: no pricing rule found for updated multiclub state, using stored fee', [
            'transition_id'              => $transition->id,
            'target_membership_type_id'  => $targetMembershipTypeId,
            'from_membership_type_id'    => $fromMembershipTypeId,
            'current_has_multiple_clubs' => $currentlyHasMultipleClubs,
            'stored_has_multiple_clubs'  => $transition->has_multiple_clubs,
            'fallback_fee'               => $transition->monthly_fee,
        ]);

        return (float) $transition->monthly_fee;
    }

    private function findExistingAccountGroup(int $memberId, int $currentClubId): ?MembershipAccountGroup
    {
        $existing = Membership::query()
            ->where('status', 'active')
            ->where('is_primary', true)
            ->where('club_id', '!=', $currentClubId)
            ->whereHas('account.accountMembers', fn (Builder $q) =>
                $q->where('member_id', $memberId)->where('is_primary_holder', true)
            )
            ->with('account.accountGroup')
            ->first();

        return $existing?->account?->accountGroup;
    }

    private function generateMembershipNumber(\App\Models\Administrator\Club $club): string
    {
        return sprintf('%s-%s', strtoupper($club->code ?: 'MEM'), now()->format('YmdHisv'));
    }

    private function uploadTransitionDocuments(array $memberDocuments): void
    {
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
                        $uploaded  = Storage::disk('spaces')->putFileAs($directory, $file, $filename);

                        if ($uploaded === false) {
                            Log::error('Age transition document upload returned false', [
                                'member_id'        => $memberId,
                                'document_type_id' => $documentTypeId,
                            ]);
                            continue;
                        }

                        MemberDocument::create([
                            'member_id'        => $memberId,
                            'document_type_id' => $documentTypeId,
                            'file_path'        => "{$directory}/{$filename}",
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Age transition document upload failed', [
                            'member_id'        => $memberId,
                            'document_type_id' => $documentTypeId,
                            'error'            => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    private function formatTransition(PendingAgeTransition $t): array
    {
        $member = $t->member;
        $name   = $member
            ? trim(collect([$member->first_name, $member->last_name, $member->second_last_name])->filter()->implode(' '))
            : 'N/D';

        $age = null;
        if ($member?->birthdate) {
            $age = (int) \Carbon\Carbon::parse($member->birthdate)->diffInYears(now());
        }

        return [
            'id'                        => $t->id,
            'member_id'                 => $t->member_id,
            'member_name'               => $name,
            'age'                       => $age,
            'membership_id'             => $t->membership_id,
            'membership_account_id'     => $t->membership_account_id,
            'club_code'                 => $t->membership?->club?->code,
            'from_membership_type'      => $t->membership?->membershipType?->name,
            'target_membership_type'    => $t->targetMembershipType?->name,
            'target_membership_type_id' => $t->target_membership_type_id,
            'transition_type'           => $t->transition_type,
            'monthly_fee'               => (float) $t->monthly_fee,
            'has_multiple_clubs'        => (bool) $t->has_multiple_clubs,
            'status'                    => $t->status,
            'identified_at'             => $t->identified_at?->toDateTimeString(),
            'promoted_at'               => $t->promoted_at?->toDateTimeString(),
            'promoted_by_name'          => $t->promotedBy?->name,
            'dismissed_at'              => $t->dismissed_at?->toDateTimeString(),
            'dismissed_by_name'         => $t->dismissedBy?->name,
            'dismissal_reason'          => $t->dismissal_reason,
        ];
    }
}
