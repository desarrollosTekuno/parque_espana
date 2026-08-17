<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\AnnualDiscountRule;
use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\InterclubPackageRuleFeeHistory;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use App\Models\Memberships\PricingRuleFeeHistory;
use App\Rules\ExistsInSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $this->middleware('permission:fee-schedules.store')->only([
            'store',
            'storeAnnualDiscountRule',
            'updateAnnualDiscountRule',
            'destroyAnnualDiscountRule',
        ]);
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
                'familyGroups' => $rules['familyGroups'],
                'annualDiscountRules' => $this->resolveAnnualDiscountRules($year),
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
                'annualDiscountRules' => [],
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
        $pricingRuleModels = PricingRule::query()
            ->with(['membershipType', 'fromMembershipType', 'feeHistory'])
            ->whereHas('membershipType', fn ($q) => $q->where('club_id', $clubId))
            ->get();

        $pricingRules = $pricingRuleModels
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
            'familyGroups' => $this->buildFamilyGroups($pricingRuleModels),
        ];
    }

    /**
     * Arma la captura rápida del módulo: un solo grupo "General" que junta los
     * ids de Familiar/Individual/Solidaria de TODAS las categorías del club
     * (Externos, Ascendencia, Beneficencia, etc.) — en la práctica esas
     * categorías siempre cobran la misma mensualidad entre sí (solo cambia la
     * inscripción, que no se toca aquí), así que se editan como una sola cosa.
     * "Pase Mensual" es la única excepción real (cobra distinto) y se aísla en
     * su propio grupo, detectado por nombre ya que no hay una bandera en BD
     * para eso.
     */
    protected function buildFamilyGroups(Collection $pricingRuleModels): array
    {
        $familiarTypes = $pricingRuleModels
            ->map(fn (PricingRule $r) => $r->membershipType)
            ->filter(fn (?MembershipType $t) => $t && $t->allows_multiple_members)
            ->unique('id')
            ->values();

        [$monthlyPassTypes, $normalTypes] = $familiarTypes->partition(
            fn (MembershipType $t) => $this->isMonthlyPass($t->name)
        );

        $groups = [];

        if ($normalTypes->isNotEmpty()) {
            $groups[] = $this->buildCombinedGroup($pricingRuleModels, $normalTypes, 'Mensualidad estándar');
        }

        if ($monthlyPassTypes->isNotEmpty()) {
            $groups[] = $this->buildCombinedGroup($pricingRuleModels, $monthlyPassTypes, 'Pase mensual');
        }

        return $groups;
    }

    /**
     * Junta, para un conjunto de tipos Familiar, los ids de todas las reglas
     * (de todas sus categorías) que caen en cada uno de los 6 "cajones":
     * Familiar/Individual/Solidaria × este parque/ambos parques.
     */
    protected function buildCombinedGroup(Collection $pricingRuleModels, Collection $familiarTypes, string $label): array
    {
        $familiarSingle = collect();
        $familiarMulti = collect();
        $individualSingle = collect();
        $individualMulti = collect();
        $solidariaSingle = collect();
        $solidariaMulti = collect();
        $hasIndividual = false;
        $hasSolidaria = false;

        foreach ($familiarTypes as $familiarType) {
            $familiarSlot = $this->buildSlot($pricingRuleModels, $familiarType);
            $familiarSingle = $familiarSingle->merge($familiarSlot['single_rule_ids']);
            $familiarMulti = $familiarMulti->merge($familiarSlot['multiclub_rule_ids']);

            $fromFamiliar = $pricingRuleModels->filter(
                fn (PricingRule $r) => $r->from_membership_type_id === $familiarType->id
            );

            $individualType = $fromFamiliar
                ->first(fn (PricingRule $r) => !$r->requires_origin_family)
                ?->membershipType;

            $solidariaType = $fromFamiliar
                ->first(fn (PricingRule $r) => $r->requires_origin_family)
                ?->membershipType;

            if ($individualType) {
                $hasIndividual = true;
                $slot = $this->buildSlot($pricingRuleModels, $individualType);
                $individualSingle = $individualSingle->merge($slot['single_rule_ids']);
                $individualMulti = $individualMulti->merge($slot['multiclub_rule_ids']);
            }

            if ($solidariaType) {
                $hasSolidaria = true;
                $slot = $this->buildSlot($pricingRuleModels, $solidariaType);
                $solidariaSingle = $solidariaSingle->merge($slot['single_rule_ids']);
                $solidariaMulti = $solidariaMulti->merge($slot['multiclub_rule_ids']);
            }
        }

        return [
            'label' => $label,
            'familiar' => [
                'membership_type_name' => 'Familiar',
                'single_rule_ids' => $familiarSingle->unique()->values(),
                'multiclub_rule_ids' => $familiarMulti->unique()->values(),
            ],
            'individual' => $hasIndividual ? [
                'membership_type_name' => 'Individual',
                'single_rule_ids' => $individualSingle->unique()->values(),
                'multiclub_rule_ids' => $individualMulti->unique()->values(),
            ] : null,
            'solidaria' => $hasSolidaria ? [
                'membership_type_name' => 'Solidaria',
                'single_rule_ids' => $solidariaSingle->unique()->values(),
                'multiclub_rule_ids' => $solidariaMulti->unique()->values(),
            ] : null,
        ];
    }

    protected function buildSlot(Collection $pricingRuleModels, MembershipType $targetType): array
    {
        $rulesForType = $pricingRuleModels->where('membership_type_id', $targetType->id);

        return [
            'membership_type_name' => $targetType->name,
            'single_rule_ids' => $rulesForType->where('requires_multiple_clubs', false)->pluck('id')->values(),
            'multiclub_rule_ids' => $rulesForType->where('requires_multiple_clubs', true)->pluck('id')->values(),
        ];
    }

    /**
     * El nombre dice "mensual" pero en realidad se cobra igual que cualquier
     * otra membresía (misma cadencia mensual) — la diferencia real es que su
     * tarifa es distinta a las demás categorías, por eso se aísla aquí.
     */
    protected function isMonthlyPass(string $membershipTypeName): bool
    {
        return str_contains(mb_strtolower($membershipTypeName), 'pase mensual');
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

    /**
     * Reglas de descuento por pago de anualidad (billing.annual_discount_rules)
     * vigentes para $year — no están ligadas a un club (aplican a cualquier
     * parque, ver AnnualPaymentService::processAnnualPayment /
     * CollectionController::previewAnnualPayment), así que se muestran igual
     * sin importar el parque de la sesión.
     */
    protected function resolveAnnualDiscountRules(int $year): Collection
    {
        return AnnualDiscountRule::query()
            ->where('year', $year)
            ->orderBy('pay_by_month')
            ->get()
            ->map(fn (AnnualDiscountRule $rule) => [
                'id' => $rule->id,
                'year' => $rule->year,
                'pay_by_month' => $rule->pay_by_month,
                'discount_months' => (float) $rule->discount_months,
                'free_month' => $rule->free_month,
                'is_active' => (bool) $rule->is_active,
            ])
            ->values();
    }

    public function storeAnnualDiscountRule(Request $request)
    {
        try {
            $validated = $this->validateAnnualDiscountRule($request);

            AnnualDiscountRule::create($validated);

            return redirect()->back()->with('success', 'Regla de descuento por anualidad creada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear la regla.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function updateAnnualDiscountRule(Request $request, AnnualDiscountRule $annualDiscountRule)
    {
        try {
            $validated = $this->validateAnnualDiscountRule($request, $annualDiscountRule);

            $annualDiscountRule->update($validated);

            return redirect()->back()->with('success', 'Regla de descuento por anualidad actualizada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar la regla.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroyAnnualDiscountRule(AnnualDiscountRule $annualDiscountRule)
    {
        try {
            $annualDiscountRule->delete();

            return redirect()->back()->with('success', 'Regla de descuento por anualidad eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar la regla.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function validateAnnualDiscountRule(Request $request, ?AnnualDiscountRule $rule = null): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'pay_by_month' => [
                'required', 'integer', 'min:1', 'max:12',
                Rule::unique(AnnualDiscountRule::class, 'pay_by_month')
                    ->where(fn ($query) => $query->where('year', $request->input('year')))
                    ->ignore($rule?->id),
            ],
            'discount_months' => ['required', 'numeric', 'min:0', 'max:12'],
            'free_month' => ['required', 'integer', 'min:1', 'max:12'],
            'is_active' => ['required', 'boolean'],
        ]);
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
