<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Administrator\Club;
use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\InterclubPackageRuleFeeHistory;
use App\Models\Memberships\PricingRule;
use App\Models\Memberships\PricingRuleFeeHistory;
use App\Rules\ExistsInSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Módulo "Cuotas por año": junta en una sola pantalla, agrupadas por año,
 * las cuotas de TODAS las reglas de precio y paquetes interclub del club
 * actual. Antes había que editar cada regla una por una en sus propias
 * pantallas (Reglas de precio / Paquetes interclub), lo cual era tedioso
 * para un ajuste anual y no dejaba historial. Aquí se ve/edita todo junto y
 * cada guardado deja un registro explícito para ese año.
 */
class FeeScheduleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:fee-schedules.index')->only(['index', 'preview']);
        $this->middleware('permission:fee-schedules.store')->only('store');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->input('club_id') ?? session('club_id'));
            $year = (int) ($request->input('year') ?? now()->year);

            $rules = $this->resolveRules($clubId, $year);

            $currentClub = Club::query()->select('id', 'name', 'code')->find($clubId);

            return Inertia::render('AdminClubs/FeeSchedules/Index', [
                'pricingRules' => $rules['pricingRules'],
                'interclubRules' => $rules['interclubRules'],
                'year' => $year,
                'currentClub' => $currentClub ? [
                    'id' => $currentClub->id,
                    'name' => $currentClub->name,
                    'code' => $currentClub->code,
                ] : null,
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/FeeSchedules/Index', [
                'pricingRules' => [],
                'interclubRules' => [],
                'year' => (int) ($request->input('year') ?? now()->year),
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Vista previa en JSON de las cuotas vigentes para un año, usada por el
     * botón "Copiar cuotas de otro año" (no navega, solo trae los valores
     * para que el admin los revise antes de guardarlos en el año actual).
     */
    public function preview(Request $request): JsonResponse
    {
        $clubId = (int) ($request->input('club_id') ?? session('club_id'));
        $year = (int) $request->input('year', now()->year);

        return response()->json($this->resolveRules($clubId, $year));
    }

    protected function resolveRules(int $clubId, int $year): array
    {
        $pricingRules = PricingRule::query()
            ->with(['membershipType', 'fromMembershipType', 'feeHistory'])
            ->whereHas('membershipType', fn ($q) => $q->where('club_id', $clubId))
            ->get()
            ->map(fn (PricingRule $rule) => $this->mapPricingRule($rule, $year))
            ->sortBy('membership_type_name')
            ->values();

        $interclubRules = InterclubPackageRule::query()
            ->with(['sourceClub', 'targetClub', 'sourceMembershipType', 'targetMembershipType', 'feeHistory'])
            ->where('target_club_id', $clubId)
            ->get()
            ->map(fn (InterclubPackageRule $rule) => $this->mapInterclubRule($rule, $year))
            ->sortBy('target_membership_type_name')
            ->values();

        return [
            'pricingRules' => $pricingRules,
            'interclubRules' => $interclubRules,
        ];
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => ['required', 'integer', 'min:2000', 'max:2100'],
                'pricing_rules' => ['array'],
                'pricing_rules.*.id' => ['required', 'integer', new ExistsInSchema('memberships', 'pricing_rules', 'id')],
                'pricing_rules.*.monthly_fee' => ['required', 'numeric', 'min:0'],
                'pricing_rules.*.inscription_fee' => ['nullable', 'numeric', 'min:0'],
                'interclub_rules' => ['array'],
                'interclub_rules.*.id' => ['required', 'integer', new ExistsInSchema('memberships', 'interclub_package_rules', 'id')],
                'interclub_rules.*.monthly_fee' => ['required', 'numeric', 'min:0'],
                'interclub_rules.*.inscription_fee' => ['required', 'numeric', 'min:0'],
            ]);

            $clubId = (int) session('club_id');
            $year = (int) $validated['year'];

            DB::transaction(function () use ($validated, $clubId, $year) {
                $pricingRuleIds = PricingRule::query()
                    ->whereHas('membershipType', fn ($q) => $q->where('club_id', $clubId))
                    ->pluck('id');

                foreach ($validated['pricing_rules'] ?? [] as $row) {
                    if (!$pricingRuleIds->contains((int) $row['id'])) {
                        continue;
                    }

                    PricingRuleFeeHistory::updateOrCreate(
                        ['pricing_rule_id' => $row['id'], 'year' => $year],
                        [
                            'monthly_fee' => (float) $row['monthly_fee'],
                            'inscription_fee' => isset($row['inscription_fee']) ? (float) $row['inscription_fee'] : null,
                        ]
                    );
                }

                $interclubRuleIds = InterclubPackageRule::query()
                    ->where('target_club_id', $clubId)
                    ->pluck('id');

                foreach ($validated['interclub_rules'] ?? [] as $row) {
                    if (!$interclubRuleIds->contains((int) $row['id'])) {
                        continue;
                    }

                    InterclubPackageRuleFeeHistory::updateOrCreate(
                        ['interclub_package_rule_id' => $row['id'], 'year' => $year],
                        [
                            'monthly_fee' => (float) $row['monthly_fee'],
                            'inscription_fee' => (float) $row['inscription_fee'],
                        ]
                    );
                }
            });

            return redirect()->back()->with('success', "Cuotas de {$year} guardadas correctamente.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al guardar las cuotas.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function mapPricingRule(PricingRule $rule, int $year): array
    {
        return [
            'id' => $rule->id,
            'membership_type_name' => $rule->membershipType?->name,
            'membership_type_code' => $rule->membershipType?->code,
            'from_membership_type_id' => $rule->from_membership_type_id,
            'from_membership_type_name' => $rule->fromMembershipType?->name,
            'from_membership_type_code' => $rule->fromMembershipType?->code,
            'min_age' => $rule->min_age,
            'max_age' => $rule->max_age,
            'requires_multiple_clubs' => (bool) $rule->requires_multiple_clubs,
            'priority' => (int) $rule->priority,
            'is_active' => (bool) $rule->is_active,
            'monthly_fee' => $rule->resolveMonthlyFee($year),
            'inscription_fee' => $rule->resolveInscriptionFee($year),
            'has_explicit_year' => $rule->feeHistory->contains(fn ($h) => $h->year === $year),
        ];
    }

    protected function mapInterclubRule(InterclubPackageRule $rule, int $year): array
    {
        return [
            'id' => $rule->id,
            'source_club_code' => $rule->sourceClub?->code,
            'source_membership_type_name' => $rule->sourceMembershipType?->name,
            'target_membership_type_name' => $rule->targetMembershipType?->name,
            'target_membership_type_code' => $rule->targetMembershipType?->code,
            'package_code' => $rule->package_code,
            'priority' => (int) $rule->priority,
            'is_active' => (bool) $rule->is_active,
            'monthly_fee' => $rule->resolveMonthlyFee($year),
            'inscription_fee' => $rule->resolveInscriptionFee($year),
            'has_explicit_year' => $rule->feeHistory->contains(fn ($h) => $h->year === $year),
        ];
    }
}
