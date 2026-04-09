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
    public function index()
    {
        //$items = Model::get();
        //return Inertia::render('Ruta/Vista', compact('items'));
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

    public function store(Request $request)
    {
        $clubId = session('club_id');

        if (!$clubId) {
            return redirect()->back()->withErrors([
                'messageError' => 'No hay un club seleccionado en la sesion.',
                'exception' => '',
            ]);
        }

        $validated = $request->validate([
            'membership_type_id' => ['required', new ExistsInSchema('memberships', 'types', 'id')],
            'from_membership_type_id' => ['nullable', new ExistsInSchema('memberships', 'types', 'id')],
            'source_club_id' => ['nullable', new ExistsInSchema('clubs', 'clubs', 'id')],
            'has_multiple_clubs' => ['nullable', 'boolean'],
            'source_membership_is_active' => ['nullable', 'boolean'],
            'years_in_source_club' => ['nullable', 'integer', 'min:0', 'max:99'],
            'members' => ['required', 'array', 'min:1'],
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

        $membershipType = MembershipType::where('id', $validated['membership_type_id'])
            ->where('club_id', $clubId)
            ->first();

        if (!$membershipType) {
            throw ValidationException::withMessages([
                'membership_type_id' => 'La membresia seleccionada no pertenece al club actual.',
            ]);
        }

        $fromMembershipType = null;
        if (!empty($validated['from_membership_type_id'])) {
            $fromMembershipType = MembershipType::find($validated['from_membership_type_id']);
        }

        $sourceClubId = $validated['source_club_id'] ?? $fromMembershipType?->club_id;
        $sourceClub = $sourceClubId ? Club::find($sourceClubId) : null;

        if ($sourceClub && $fromMembershipType && $fromMembershipType->club_id !== $sourceClub->id) {
            throw ValidationException::withMessages([
                'from_membership_type_id' => 'La membresia de origen no pertenece al club de origen seleccionado.',
            ]);
        }

        $primaryMembers = collect($validated['members'])
            ->where('is_primary_holder', true)
            ->values();

        if ($primaryMembers->count() !== 1) {
            throw ValidationException::withMessages([
                'members' => 'Debe existir exactamente un titular en la solicitud.',
            ]);
        }

        if (!$membershipType->allows_multiple_members && count($validated['members']) > 1) {
            throw ValidationException::withMessages([
                'members' => 'La membresia seleccionada no permite multiples integrantes.',
            ]);
        }

        foreach ($validated['members'] as $index => $memberData) {
            if (empty($memberData['is_primary_holder']) && empty($memberData['relationship_id'])) {
                throw ValidationException::withMessages([
                    "members.$index.relationship_id" => 'El parentesco es obligatorio para familiares.',
                ]);
            }
        }

        $primaryMember = $primaryMembers->first();
        $primaryAge = $this->resolveAge($primaryMember);
        $hasMultipleClubs = (bool) ($validated['has_multiple_clubs'] ?? false);
        $sourceMembershipIsActive = (bool) ($validated['source_membership_is_active'] ?? false);
        $yearsInSourceClub = array_key_exists('years_in_source_club', $validated)
            && $validated['years_in_source_club'] !== null
            ? (int) $validated['years_in_source_club']
            : null;

        if ($membershipType->requires_origin_family && !$fromMembershipType) {
            throw ValidationException::withMessages([
                'from_membership_type_id' => 'La membresia seleccionada requiere una membresia familiar de origen.',
            ]);
        }

        if ($membershipType->requires_origin_family && !$fromMembershipType->allows_multiple_members) {
            throw ValidationException::withMessages([
                'from_membership_type_id' => 'La membresia seleccionada debe provenir de una membresia familiar.',
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

        try {
            DB::transaction(function () use ($validated, $membershipType, $pricing, $clubId, $club, $fromMembershipType) {
                $membershipAccount = MembershipAccount::create([
                    'membership_number' => $this->generateMembershipNumber($club),
                    'account_type' => $membershipType->allows_multiple_members ? 'family' : 'individual',
                    'status' => 'pending',
                ]);

                foreach ($validated['members'] as $memberData) {
                    $member = Member::create([
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
                    ]);

                    if ($this->hasFilledValues($memberData['address'] ?? [])) {
                        Address::create([
                            'member_id' => $member->id,
                            'street' => $memberData['address']['street'] ?? null,
                            'neighborhood' => $memberData['address']['neighborhood'] ?? null,
                            'postal_code' => $memberData['address']['postal_code'] ?? null,
                            'city' => $memberData['address']['city'] ?? null,
                            'state' => $memberData['address']['state'] ?? null,
                            'country' => $memberData['address']['country'] ?? null,
                            'years_in_city' => $memberData['address']['years_in_city'] ?? null,
                            'is_primary' => true,
                        ]);
                    }

                    if ($this->hasFilledValues($memberData['employment'] ?? [])) {
                        EmploymentInfo::create([
                            'member_id' => $member->id,
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
                    'monthly_fee' => $pricing['monthly_fee'],
                    'start_date' => now()->toDateString(),
                    'end_date' => $membershipType->validity_months
                        ? now()->addMonthsNoOverflow($membershipType->validity_months)->toDateString()
                        : null,
                    'status' => 'pending',
                ]);
            });

            return redirect()
                ->route('members.create')
                ->with('success', 'La cuenta de membresia y sus integrantes se registraron correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrio un error al guardar la membresia y sus integrantes.',
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
                'membership_type_id' => 'No se encontro una regla de costo aplicable para la membresia seleccionada.',
            ]);
        }

        return [
            'monthly_fee' => (float) $pricingRule->monthly_fee,
            'inscription_fee' => (float) ($pricingRule->inscription_fee ?? 0),
            'rule_type' => 'pricing_rule',
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
                'source_club_id' => 'El paquete Parque Espana 1 solo aplica para socios provenientes de PE1.',
            ]);
        }

        if ($yearsInSourceClub === null || $yearsInSourceClub < 5) {
            throw ValidationException::withMessages([
                'years_in_source_club' => 'El paquete Parque Espana 1 requiere al menos 5 anos de antiguedad en PE1.',
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
}
