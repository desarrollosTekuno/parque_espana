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
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountMember;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Rules\ExistsInSchema;
use Carbon\Carbon;
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
        // membership types
        // exist session club_id
        $relationships = Relationship::select('id', 'name')->get();
        $nationalities = Nationality::select('id', 'code', 'name', 'demonym')
            ->orderBy('name')
            ->get();
        $maritalStatuses = MaritalStatus::select('id', 'code', 'name')
            ->orderBy('name')
            ->get();
        $membershipTypes = MembershipType::where('show_in_listing', true)
            ->with([
                'documentTypes:id,name,allowed_extensions',
                'documentTypes.relationships:id,name',
            ])
            ->where('club_id', session('club_id'))
            ->orderBy('created_at','desc')
            ->get();
        return Inertia::render('Members/Create', compact(
            'membershipTypes',
            'relationships',
            'nationalities',
            'maritalStatuses'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $clubId = session('club_id');

        if (!$clubId) {
            return redirect()->back()->withErrors([
                'messageError' => 'No hay un club seleccionado en la sesión.',
                'exception' => '',
            ]);
        }

        $validated = $request->validate([
            'membership_type_id' => ['required', new ExistsInSchema('memberships', 'types', 'id')],
            'from_membership_type_id' => ['nullable', new ExistsInSchema('memberships', 'types', 'id')],
            'members' => ['required', 'array', 'min:1'],
            'members.*.first_name' => ['required', 'string', 'max:255'],
            'members.*.last_name' => ['required', 'string', 'max:255'],
            'members.*.second_last_name' => ['nullable', 'string', 'max:255'],
            'members.*.birthdate' => ['nullable', 'date'],
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
                'membership_type_id' => 'La membresía seleccionada no pertenece al club actual.',
            ]);
        }

        $primaryMembers = collect($validated['members'])->where('is_primary_holder', true)->values();

        if ($primaryMembers->count() !== 1) {
            throw ValidationException::withMessages([
                'members' => 'Debe existir exactamente un titular en la solicitud.',
            ]);
        }

        if (!$membershipType->allows_multiple_members && count($validated['members']) > 1) {
            throw ValidationException::withMessages([
                'members' => 'La membresía seleccionada no permite múltiples integrantes.',
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

        $pricingRule = $this->resolvePricingRule(
            membershipTypeId: $membershipType->id,
            fromMembershipTypeId: $validated['from_membership_type_id'] ?? null,
            age: $primaryAge
        );

        if (!$pricingRule) {
            throw ValidationException::withMessages([
                'membership_type_id' => 'No se encontró una regla de costo aplicable para la membresía seleccionada.',
            ]);
        }

        $club = Club::findOrFail($clubId);

        try {
            DB::transaction(function () use ($validated, $membershipType, $pricingRule, $clubId, $club) {
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
                    'origin_membership_type_id' => $validated['from_membership_type_id'] ?? null,
                    'is_primary' => true,
                    'monthly_fee' => $pricingRule->monthly_fee,
                    'start_date' => now()->toDateString(),
                    'end_date' => $membershipType->validity_months
                        ? now()->addMonthsNoOverflow($membershipType->validity_months)->toDateString()
                        : null,
                    'status' => 'pending',
                ]);
            });

            return redirect()
                ->route('members.create')
                ->with('success', 'La cuenta de membresía y sus integrantes se registraron correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al guardar la membresía y sus integrantes.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
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

    /**
     * Remove the specified resource from storage.
     */
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

    protected function resolvePricingRule(int $membershipTypeId, ?int $fromMembershipTypeId, ?int $age): ?PricingRule
    {
        return PricingRule::query()
            ->where('membership_type_id', $membershipTypeId)
            ->when(
                $fromMembershipTypeId,
                fn ($query) => $query->where('from_membership_type_id', $fromMembershipTypeId),
                fn ($query) => $query->whereNull('from_membership_type_id')
            )
            ->when($age !== null, function ($query) use ($age) {
                $query->where(function ($ageQuery) use ($age) {
                    $ageQuery->whereNull('min_age')->orWhere('min_age', '<=', $age);
                })->where(function ($ageQuery) use ($age) {
                    $ageQuery->whereNull('max_age')->orWhere('max_age', '>=', $age);
                });
            })
            ->where('requires_multiple_clubs', false)
            ->orderBy('priority')
            ->first();
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
