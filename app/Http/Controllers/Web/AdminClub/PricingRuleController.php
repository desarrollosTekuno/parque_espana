<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Rules\ExistsInSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PricingRuleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();
            $prefix = 'pricingRules';

            $query = PricingRule::query()
                ->with([
                    'membershipType.club',
                    'fromMembershipType.club',
                ])
                ->whereHas('membershipType', function (Builder $membershipTypeQuery) use ($clubId) {
                    $membershipTypeQuery->where('club_id', $clubId);
                });

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->whereHas('membershipType', function (Builder $membershipTypeQuery) use ($search, $like) {
                        $membershipTypeQuery->where('name', $like, "%{$search}%")
                            ->orWhere('code', $like, "%{$search}%");
                    })->orWhereHas('fromMembershipType', function (Builder $membershipTypeQuery) use ($search, $like) {
                        $membershipTypeQuery->where('name', $like, "%{$search}%")
                            ->orWhere('code', $like, "%{$search}%");
                    });
                });
            }

            if (($membershipTypeId = $request->input("{$prefix}_membership_type_id")) !== null && $membershipTypeId !== '') {
                $query->where('membership_type_id', $membershipTypeId);
            }

            if (($fromMembershipTypeId = $request->input("{$prefix}_from_membership_type_id")) !== null && $fromMembershipTypeId !== '') {
                if ($fromMembershipTypeId === 'none') {
                    $query->whereNull('from_membership_type_id');
                } else {
                    $query->where('from_membership_type_id', $fromMembershipTypeId);
                }
            }

            if (($requiresMultipleClubs = $request->input("{$prefix}_requires_multiple_clubs")) !== null && $requiresMultipleClubs !== '') {
                $query->where('requires_multiple_clubs', filter_var($requiresMultipleClubs, FILTER_VALIDATE_BOOLEAN));
            }

            if (($activeOnly = $request->input("{$prefix}_is_active")) !== null && $activeOnly !== '') {
                $query->where('is_active', filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN));
            }

            $sortMap = [
                'id' => 'id',
                'priority' => 'priority',
                'created_at' => 'created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'priority');
            $order = $request->input("{$prefix}_order", 'asc');
            $sortColumn = $sortMap[$sort] ?? 'priority';

            $pricingRules = $query
                ->orderBy($sortColumn, $order)
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (PricingRule $pricingRule) {
                    return [
                        'id' => $pricingRule->id,
                        'membership_type_id' => $pricingRule->membership_type_id,
                        'membership_type_name' => $pricingRule->membershipType?->name,
                        'membership_type_code' => $pricingRule->membershipType?->code,
                        'membership_type_club_name' => $pricingRule->membershipType?->club?->name,
                        'from_membership_type_id' => $pricingRule->from_membership_type_id,
                        'from_membership_type_name' => $pricingRule->fromMembershipType?->name,
                        'from_membership_type_code' => $pricingRule->fromMembershipType?->code,
                        'from_membership_type_club_name' => $pricingRule->fromMembershipType?->club?->name,
                        'min_age' => $pricingRule->min_age,
                        'max_age' => $pricingRule->max_age,
                        'requires_origin_family' => (bool) $pricingRule->requires_origin_family,
                        'requires_multiple_clubs' => (bool) $pricingRule->requires_multiple_clubs,
                        'monthly_fee' => $pricingRule->resolveMonthlyFee(),
                        'inscription_fee' => $pricingRule->resolveInscriptionFee(),
                        'priority' => (int) $pricingRule->priority,
                        'valid_from' => $pricingRule->valid_from,
                        'valid_until' => $pricingRule->valid_until,
                        'is_active' => (bool) $pricingRule->is_active,
                    ];
                })
                ->appends($request->all());

            $targetMembershipTypes = MembershipType::query()
                ->select('id', 'club_id', 'name', 'code')
                ->with('club')
                ->where('club_id', $clubId)
                ->orderBy('name')
                ->get()
                ->map(fn (MembershipType $membershipType) => [
                    'id' => $membershipType->id,
                    'name' => $membershipType->name,
                    'code' => $membershipType->code,
                    'club_name' => $membershipType->club?->name,
                ]);

            $originMembershipTypes = MembershipType::query()
                ->select('id', 'club_id', 'name', 'code')
                ->with('club')
                ->orderBy('name')
                ->get()
                ->map(fn (MembershipType $membershipType) => [
                    'id' => $membershipType->id,
                    'name' => $membershipType->name,
                    'code' => $membershipType->code,
                    'club_name' => $membershipType->club?->name,
                ]);

            return Inertia::render('AdminClubs/PricingRules/Index', [
                'pricingRules' => $pricingRules,
                'targetMembershipTypes' => $targetMembershipTypes,
                'originMembershipTypes' => $originMembershipTypes,
                'filters' => [
                    'search' => $request->input("{$prefix}_search"),
                    'membership_type_id' => $request->input("{$prefix}_membership_type_id"),
                    'from_membership_type_id' => $request->input("{$prefix}_from_membership_type_id"),
                    'requires_multiple_clubs' => $request->input("{$prefix}_requires_multiple_clubs"),
                    'is_active' => $request->input("{$prefix}_is_active"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/PricingRules/Index', [
                'pricingRules' => [
                    'data' => [],
                    'total' => 0,
                ],
                'targetMembershipTypes' => [],
                'originMembershipTypes' => [],
                'filters' => [
                    'search' => $request->input('pricingRules_search'),
                    'membership_type_id' => $request->input('pricingRules_membership_type_id'),
                    'from_membership_type_id' => $request->input('pricingRules_from_membership_type_id'),
                    'requires_multiple_clubs' => $request->input('pricingRules_requires_multiple_clubs'),
                    'is_active' => $request->input('pricingRules_is_active'),
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validatePricingRule($request);

            $this->ensurePricingRuleBelongsToCurrentClub((int) $validated['membership_type_id']);
            $this->ensurePricingRuleUniqueness($validated);

            PricingRule::create($validated);

            return redirect()->back()->with('success', 'Regla de precio creada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear la regla de precio.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, PricingRule $pricingRule)
    {
        try {
            $this->ensurePricingRuleIsInCurrentClub($pricingRule);

            $validated = $this->validatePricingRule($request, $pricingRule);

            $this->ensurePricingRuleBelongsToCurrentClub((int) $validated['membership_type_id']);
            $this->ensurePricingRuleUniqueness($validated, $pricingRule);

            $pricingRule->update($validated);

            return redirect()->back()->with('success', 'Regla de precio actualizada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la regla de precio.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(PricingRule $pricingRule)
    {
        try {
            $this->ensurePricingRuleIsInCurrentClub($pricingRule);
            $pricingRule->delete();

            return redirect()->back()->with('success', 'Regla de precio eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar la regla de precio.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function validatePricingRule(Request $request, ?PricingRule $pricingRule = null): array
    {
        $validated = $request->validate([
            'membership_type_id' => ['required', new ExistsInSchema('memberships', 'types', 'id')],
            'from_membership_type_id' => ['nullable', new ExistsInSchema('memberships', 'types', 'id')],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'requires_origin_family' => ['required', 'boolean'],
            'requires_multiple_clubs' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:999999'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (
            array_key_exists('min_age', $validated)
            && array_key_exists('max_age', $validated)
            && $validated['min_age'] !== null
            && $validated['max_age'] !== null
            && $validated['min_age'] > $validated['max_age']
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'min_age' => 'La edad mínima no puede ser mayor a la edad máxima.',
            ]);
        }

        return $validated;
    }

    protected function ensurePricingRuleBelongsToCurrentClub(int $membershipTypeId): void
    {
        $clubId = (int) session('club_id');

        $exists = MembershipType::query()
            ->where('id', $membershipTypeId)
            ->where('club_id', $clubId)
            ->exists();

        if (!$exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'membership_type_id' => 'La membresía destino debe pertenecer al club actual.',
            ]);
        }
    }

    protected function ensurePricingRuleIsInCurrentClub(PricingRule $pricingRule): void
    {
        $pricingRule->loadMissing('membershipType');

        if ((int) ($pricingRule->membershipType?->club_id ?? 0) !== (int) session('club_id')) {
            abort(404);
        }
    }

    protected function ensurePricingRuleUniqueness(array $validated, ?PricingRule $pricingRule = null): void
    {
        $query = PricingRule::query()
            ->where('membership_type_id', $validated['membership_type_id'])
            ->when(
                $validated['from_membership_type_id'] ?? null,
                fn (Builder $builder, $value) => $builder->where('from_membership_type_id', $value),
                fn (Builder $builder) => $builder->whereNull('from_membership_type_id')
            )
            ->when(
                array_key_exists('min_age', $validated) && $validated['min_age'] !== null,
                fn (Builder $builder) => $builder->where('min_age', $validated['min_age']),
                fn (Builder $builder) => $builder->whereNull('min_age')
            )
            ->when(
                array_key_exists('max_age', $validated) && $validated['max_age'] !== null,
                fn (Builder $builder) => $builder->where('max_age', $validated['max_age']),
                fn (Builder $builder) => $builder->whereNull('max_age')
            )
            ->where('requires_origin_family', (bool) $validated['requires_origin_family'])
            ->where('requires_multiple_clubs', (bool) $validated['requires_multiple_clubs'])
            ->when(
                $validated['valid_from'] ?? null,
                fn (Builder $builder, $value) => $builder->whereDate('valid_from', $value),
                fn (Builder $builder) => $builder->whereNull('valid_from')
            )
            ->when(
                $validated['valid_until'] ?? null,
                fn (Builder $builder, $value) => $builder->whereDate('valid_until', $value),
                fn (Builder $builder) => $builder->whereNull('valid_until')
            );

        if ($pricingRule) {
            $query->where('id', '!=', $pricingRule->id);
        }

        if ($query->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'membership_type_id' => 'Ya existe una regla de precio con la misma combinación lógica.',
            ]);
        }
    }
}
