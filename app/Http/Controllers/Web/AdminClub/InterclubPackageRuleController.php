<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\MembershipType;
use App\Rules\ExistsInSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InterclubPackageRuleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();
            $prefix = 'interclubPackageRules';

            $query = InterclubPackageRule::query()
                ->with([
                    'sourceClub',
                    'targetClub',
                    'sourceMembershipType',
                    'targetMembershipType',
                ])
                ->where('target_club_id', $clubId);

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('package_code', $like, "%{$search}%")
                        ->orWhereHas('sourceClub', function (Builder $clubQuery) use ($search, $like) {
                            $clubQuery->where('name', $like, "%{$search}%")
                                ->orWhere('code', $like, "%{$search}%");
                        })
                        ->orWhereHas('targetClub', function (Builder $clubQuery) use ($search, $like) {
                            $clubQuery->where('name', $like, "%{$search}%")
                                ->orWhere('code', $like, "%{$search}%");
                        })
                        ->orWhereHas('sourceMembershipType', function (Builder $membershipTypeQuery) use ($search, $like) {
                            $membershipTypeQuery->where('name', $like, "%{$search}%")
                                ->orWhere('code', $like, "%{$search}%");
                        })
                        ->orWhereHas('targetMembershipType', function (Builder $membershipTypeQuery) use ($search, $like) {
                            $membershipTypeQuery->where('name', $like, "%{$search}%")
                                ->orWhere('code', $like, "%{$search}%");
                        });
                });
            }

            if (($sourceClubId = $request->input("{$prefix}_source_club_id")) !== null && $sourceClubId !== '') {
                $query->where('source_club_id', $sourceClubId);
            }

            if (($sourceMembershipTypeId = $request->input("{$prefix}_source_membership_type_id")) !== null && $sourceMembershipTypeId !== '') {
                if ($sourceMembershipTypeId === 'none') {
                    $query->whereNull('source_membership_type_id');
                } else {
                    $query->where('source_membership_type_id', $sourceMembershipTypeId);
                }
            }

            if (($activeOnly = $request->input("{$prefix}_is_active")) !== null && $activeOnly !== '') {
                $query->where('is_active', filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN));
            }

            $sortMap = [
                'id' => 'id',
                'priority' => 'priority',
                'monthly_fee' => 'monthly_fee',
                'created_at' => 'created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'priority');
            $order = $request->input("{$prefix}_order", 'asc');
            $sortColumn = $sortMap[$sort] ?? 'priority';

            $rules = $query
                ->orderBy($sortColumn, $order)
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(function (InterclubPackageRule $rule) {
                    return [
                        'id' => $rule->id,
                        'source_club_id' => $rule->source_club_id,
                        'source_club_name' => $rule->sourceClub?->name,
                        'source_club_code' => $rule->sourceClub?->code,
                        'target_club_id' => $rule->target_club_id,
                        'target_club_name' => $rule->targetClub?->name,
                        'target_club_code' => $rule->targetClub?->code,
                        'source_membership_type_id' => $rule->source_membership_type_id,
                        'source_membership_type_name' => $rule->sourceMembershipType?->name,
                        'source_membership_type_code' => $rule->sourceMembershipType?->code,
                        'target_membership_type_id' => $rule->target_membership_type_id,
                        'target_membership_type_name' => $rule->targetMembershipType?->name,
                        'target_membership_type_code' => $rule->targetMembershipType?->code,
                        'package_code' => $rule->package_code,
                        'min_years_in_source_club' => $rule->min_years_in_source_club,
                        'requires_active_source_membership' => (bool) $rule->requires_active_source_membership,
                        'monthly_fee' => (float) $rule->monthly_fee,
                        'inscription_fee' => (float) $rule->inscription_fee,
                        'priority' => (int) $rule->priority,
                        'valid_from' => $rule->valid_from,
                        'valid_until' => $rule->valid_until,
                        'is_active' => (bool) $rule->is_active,
                    ];
                })
                ->appends($request->all());

            $clubs = Club::query()
                ->select('id', 'code', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (Club $club) => [
                    'id' => $club->id,
                    'code' => $club->code,
                    'name' => $club->name,
                ]);

            $membershipTypes = MembershipType::query()
                ->select('id', 'club_id', 'name', 'code')
                ->orderBy('name')
                ->get()
                ->map(fn (MembershipType $membershipType) => [
                    'id' => $membershipType->id,
                    'club_id' => $membershipType->club_id,
                    'name' => $membershipType->name,
                    'code' => $membershipType->code,
                ]);

            return Inertia::render('AdminClubs/InterclubPackageRules/Index', [
                'interclubPackageRules' => $rules,
                'clubs' => $clubs,
                'membershipTypes' => $membershipTypes,
                'filters' => [
                    'search' => $request->input("{$prefix}_search"),
                    'source_club_id' => $request->input("{$prefix}_source_club_id"),
                    'source_membership_type_id' => $request->input("{$prefix}_source_membership_type_id"),
                    'is_active' => $request->input("{$prefix}_is_active"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/InterclubPackageRules/Index', [
                'interclubPackageRules' => [
                    'data' => [],
                    'total' => 0,
                ],
                'clubs' => [],
                'membershipTypes' => [],
                'filters' => [
                    'search' => $request->input('interclubPackageRules_search'),
                    'source_club_id' => $request->input('interclubPackageRules_source_club_id'),
                    'source_membership_type_id' => $request->input('interclubPackageRules_source_membership_type_id'),
                    'is_active' => $request->input('interclubPackageRules_is_active'),
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateRule($request);
            $this->ensureRuleBelongsToCurrentClub((int) $validated['target_club_id']);
            $this->ensureMembershipTypesMatchClubs($validated);
            $this->ensureUniqueness($validated);

            InterclubPackageRule::create($validated);

            return redirect()->back()->with('success', 'Paquete interclub creado correctamente.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el paquete interclub.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, InterclubPackageRule $interclubPackageRule)
    {
        try {
            $this->ensureExistingRuleBelongsToCurrentClub($interclubPackageRule);

            $validated = $this->validateRule($request);
            $this->ensureRuleBelongsToCurrentClub((int) $validated['target_club_id']);
            $this->ensureMembershipTypesMatchClubs($validated);
            $this->ensureUniqueness($validated, $interclubPackageRule);

            $interclubPackageRule->update($validated);

            return redirect()->back()->with('success', 'Paquete interclub actualizado correctamente.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el paquete interclub.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(InterclubPackageRule $interclubPackageRule)
    {
        try {
            $this->ensureExistingRuleBelongsToCurrentClub($interclubPackageRule);
            $interclubPackageRule->delete();

            return redirect()->back()->with('success', 'Paquete interclub eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar el paquete interclub.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function validateRule(Request $request): array
    {
        $validated = $request->validate([
            'source_club_id' => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
            'target_club_id' => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
            'target_membership_type_id' => ['required', new ExistsInSchema('memberships', 'types', 'id')],
            'source_membership_type_id' => ['nullable', new ExistsInSchema('memberships', 'types', 'id')],
            'package_code' => ['required', 'string', 'max:100'],
            'min_years_in_source_club' => ['nullable', 'integer', 'min:0', 'max:120'],
            'requires_active_source_membership' => ['required', 'boolean'],
            'inscription_fee' => ['required', 'numeric', 'min:0'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'priority' => ['required', 'integer', 'min:1', 'max:999999'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ((int) $validated['source_club_id'] === (int) $validated['target_club_id']) {
            throw ValidationException::withMessages([
                'target_club_id' => 'El club destino debe ser distinto al club origen.',
            ]);
        }

        return $validated;
    }

    protected function ensureRuleBelongsToCurrentClub(int $targetClubId): void
    {
        if ($targetClubId !== (int) session('club_id')) {
            throw ValidationException::withMessages([
                'target_club_id' => 'El club destino debe ser el club actual.',
            ]);
        }
    }

    protected function ensureExistingRuleBelongsToCurrentClub(InterclubPackageRule $rule): void
    {
        if ((int) $rule->target_club_id !== (int) session('club_id')) {
            abort(404);
        }
    }

    protected function ensureMembershipTypesMatchClubs(array $validated): void
    {
        $targetMatches = MembershipType::query()
            ->where('id', $validated['target_membership_type_id'])
            ->where('club_id', $validated['target_club_id'])
            ->exists();

        if (!$targetMatches) {
            throw ValidationException::withMessages([
                'target_membership_type_id' => 'La membresía destino debe pertenecer al club destino.',
            ]);
        }

        if (!empty($validated['source_membership_type_id'])) {
            $sourceMatches = MembershipType::query()
                ->where('id', $validated['source_membership_type_id'])
                ->where('club_id', $validated['source_club_id'])
                ->exists();

            if (!$sourceMatches) {
                throw ValidationException::withMessages([
                    'source_membership_type_id' => 'La membresía origen debe pertenecer al club origen.',
                ]);
            }
        }
    }

    protected function ensureUniqueness(array $validated, ?InterclubPackageRule $rule = null): void
    {
        $query = InterclubPackageRule::query()
            ->where('source_club_id', $validated['source_club_id'])
            ->where('target_club_id', $validated['target_club_id'])
            ->where('target_membership_type_id', $validated['target_membership_type_id'])
            ->when(
                $validated['source_membership_type_id'] ?? null,
                fn (Builder $builder, $value) => $builder->where('source_membership_type_id', $value),
                fn (Builder $builder) => $builder->whereNull('source_membership_type_id')
            )
            ->where('package_code', $validated['package_code']);

        if ($rule) {
            $query->where('id', '!=', $rule->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'package_code' => 'Ya existe un paquete interclub con la misma combinación lógica.',
            ]);
        }
    }
}
