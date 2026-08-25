<?php

namespace App\Services\Billing;

use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipType;
use App\Models\Memberships\PricingRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MembershipPricingService
{
    /**
     * Recalculate fees for every active primary membership that shares an account
     * group with the cancelled account. Called after an account cancellation so
     * group mates revert from the interclub/split rate to their standalone price.
     */
    public function recalculateGroupFeesAfterCancellation(
        MembershipAccount $cancelledAccount,
        MembershipChargeService $chargeService
    ): void {
        $accountGroupId = $cancelledAccount->account_group_id;

        if (!$accountGroupId) {
            return;
        }

        $remainingMemberships = Membership::query()
            ->with(['membershipType', 'account.primaryHolder.member'])
            ->where('is_primary', true)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('account', fn (Builder $q) => $q->where('account_group_id', $accountGroupId))
            // Solo las que YA representaban un combo interclub real (un
            // paquete específico, interclub_package_rule_id, o la regla
            // genérica marcada requires_multiple_clubs — mismo criterio que
            // CollectionController::resolveGroupAccountIds) — una membresía
            // que comparte grupo pero siempre fue independiente (p. ej. el
            // mismo titular con un Individual en un parque y un Pase
            // Mensual en otro, sin relación de precio real entre ambos)
            // nunca tuvo tarifa de grupo que "revertir": recalcularla aquí
            // solo arriesga sobreescribir su pricing_rule_id con uno
            // distinto al que ya tenía, sin ninguna razón real.
            ->where(fn (Builder $scope) => $scope
                ->whereNotNull('interclub_package_rule_id')
                ->orWhereHas('pricingRule', fn (Builder $pricingRule) => $pricingRule->where('requires_multiple_clubs', true)))
            ->get();

        if ($remainingMemberships->isEmpty()) {
            return;
        }

        // After cancellation the group no longer has multiple clubs active,
        // so we look up the standalone pricing rule for each remaining membership.
        foreach ($remainingMemberships as $membership) {
            $membershipType = $membership->membershipType;

            if (!$membershipType) {
                continue;
            }

            $primaryMember = $membership->account?->primaryHolder?->member;
            $age = $this->shouldApplyAgeFilter($membershipType) ? $primaryMember?->age : null;

            $pricingRule = $this->resolvePricingRule(
                membershipTypeId: $membership->membership_type_id,
                fromMembershipTypeId: $membership->origin_membership_type_id,
                age: $age,
                hasMultipleClubs: false
            );

            if (!$pricingRule) {
                Log::warning('No se encontró pricing rule standalone para membresía tras cancelación de grupo', [
                    'membership_id' => $membership->id,
                    'membership_type_id' => $membership->membership_type_id,
                ]);
                continue;
            }

            $standaloneFee = $pricingRule->resolveMonthlyFee();

            if ($standaloneFee === null) {
                Log::warning('Pricing rule standalone sin cuota capturada tras cancelación de grupo', [
                    'membership_id' => $membership->id,
                    'pricing_rule_id' => $pricingRule->id,
                ]);
                continue;
            }

            $chargeService->synchronizeMembershipFees(
                membership: $membership,
                groupTotalMonthlyFee: $standaloneFee,
                effectiveDate: null,
                billingSplitMode: 'single',
                historyReason: 'Recálculo de cuota: baja de cuenta en grupo',
                pricingRuleId: $pricingRule->id,
                interclubPackageRuleId: null
            );
        }
    }

    /**
     * Recalculate fees for all active primary memberships in the same account
     * group as the reactivated membership. Called after a reactivation so the
     * group fee split is re-established (e.g. interclub package rate).
     */
    public function recalculateGroupFeesAfterReactivation(
        Membership $reactivatedMembership,
        MembershipChargeService $chargeService
    ): void {
        // La membresía reactivada trae guardados el pricing_rule_id /
        // interclub_package_rule_id de ANTES de cancelarse — si en ese
        // entonces era parte de un combo interclub, pero el otro lado
        // (el otro parque) sigue cancelado, ese combo ya no aplica: se
        // recalcula como standalone. Sin esto, reactivar un solo lado de
        // un ex-combo seguía cobrando la tarifa combinada aunque el socio
        // ya no tuviera membresía activa en el otro parque.
        $accountGroupId = $reactivatedMembership->account?->account_group_id;
        $sibling = $accountGroupId
            ? Membership::query()
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended'])
                ->where('club_id', '!=', $reactivatedMembership->club_id)
                ->whereHas('account', fn (Builder $q) => $q->where('account_group_id', $accountGroupId))
                ->first()
            : null;
        $hasActiveSiblingInOtherClub = (bool) $sibling;

        $pricingRuleId = $reactivatedMembership->pricing_rule_id;
        $interclubPackageRuleId = $reactivatedMembership->interclub_package_rule_id;
        $groupTotalMonthlyFee = null;
        // Si nada cambia abajo, el resultado conserva lo que ya tenía: sigue
        // representando tarifa de grupo solo si YA era combo antes de la baja.
        $representsGroupRate = (bool) $interclubPackageRuleId;

        $membershipType = $reactivatedMembership->membershipType;
        $primaryMember = $reactivatedMembership->account?->primaryHolder?->member;
        $age = ($membershipType && $this->shouldApplyAgeFilter($membershipType))
            ? $primaryMember?->age
            : null;

        if ($interclubPackageRuleId && !$hasActiveSiblingInOtherClub) {
            $standaloneRule = $this->resolvePricingRule(
                membershipTypeId: $reactivatedMembership->membership_type_id,
                fromMembershipTypeId: $reactivatedMembership->origin_membership_type_id,
                age: $age,
                hasMultipleClubs: false
            );

            $standaloneFee = $standaloneRule?->resolveMonthlyFee();

            if ($standaloneRule && $standaloneFee !== null) {
                $pricingRuleId = $standaloneRule->id;
                $interclubPackageRuleId = null;
                // synchronizeMembershipFees calcula el monto a partir de
                // resolveLiveMonthlyFee() de la membresía ANTES de aplicar
                // estos rule_id — pasar el monto ya resuelto es la única
                // forma de que la cuota realmente cambie en esta llamada,
                // no solo la referencia a la regla para la próxima vez.
                $groupTotalMonthlyFee = $standaloneFee;
                $representsGroupRate = false;
            } else {
                Log::warning('No se encontró pricing rule standalone al reactivar membresía sin combo activo', [
                    'membership_id' => $reactivatedMembership->id,
                    'membership_type_id' => $reactivatedMembership->membership_type_id,
                ]);
            }
        } elseif (!$interclubPackageRuleId && $hasActiveSiblingInOtherClub) {
            // Caso inverso: esta membresía ya era standalone antes de su
            // propia baja (el otro parque ya estaba cancelado en ese
            // momento), pero ahora SÍ hay un hermano activo en el otro
            // parque — puede que ahora corresponda tarifa de combo
            // interclub. Sin esto, dos membresías del mismo grupo se
            // quedaban cobrando cada una su tarifa standalone por separado
            // (suma de ambas) en vez de la tarifa combinada única.
            //
            // Primero se busca el paquete interclub ESPECÍFICO para esta
            // combinación exacta de parque+tipo en cualquiera de las dos
            // direcciones (igual que MemberController::resolveInterclubPackageRule
            // / MembershipImporter::resolveInterclubPackageRule al dar de alta o
            // migrar) — p. ej. "Individual PE1 + Familiar PE2 = $3,650" en vez
            // de la tarifa genérica por tipo, que puede ser distinta.
            $package = $this->resolveInterclubPackageRuleBetween($reactivatedMembership, $sibling);
            $packageFee = $package?->resolveMonthlyFee();

            if ($package && $packageFee !== null) {
                $reactivatedIsPackageTarget = (int) $package->target_club_id === (int) $reactivatedMembership->club_id
                    && (int) $package->target_membership_type_id === (int) $reactivatedMembership->membership_type_id;

                if ($reactivatedIsPackageTarget) {
                    $pricingRuleId = null;
                    $interclubPackageRuleId = $package->id;
                    $groupTotalMonthlyFee = $packageFee;
                    $representsGroupRate = true;
                } else {
                    // El paquete específico designa al HERMANO como lado
                    // facturable (target), no a la membresía que se está
                    // reactivando — se actualiza al hermano directamente y
                    // la reactivada se deja como origen no facturable.
                    $chargeService->synchronizeMembershipFees(
                        membership: $sibling,
                        groupTotalMonthlyFee: $packageFee,
                        historyReason: 'Recálculo de cuota: reactivación de cuenta en grupo',
                        pricingRuleId: null,
                        interclubPackageRuleId: $package->id
                    );

                    if ($reactivatedMembership->is_billable) {
                        $reactivatedMembership->update(['is_billable' => false]);
                    }

                    return;
                }
            } else {
                // No hay paquete específico capturado para esta combinación:
                // cae a la tarifa de combo genérica por tipo (mismo criterio
                // que el alta manual cuando tampoco hay paquete específico).
                $comboRule = $this->resolvePricingRule(
                    membershipTypeId: $reactivatedMembership->membership_type_id,
                    fromMembershipTypeId: $sibling->membership_type_id,
                    age: $age,
                    hasMultipleClubs: true
                );

                // resolvePricingRule puede devolver la regla "comodín"
                // (from_membership_type_id nulo — aplica sin importar el
                // tipo del hermano, ver buildPricingRuleQuery). Esa solo se
                // acepta si el tipo del HERMANO también está pensado para
                // combos interclub (tiene al menos una regla propia
                // requires_multiple_clubs=true) — si no, dos productos sin
                // relación real (p. ej. Individual + Pase Mensual
                // Individual) terminaban combinándose solo porque este lado
                // sí tiene tarifa combo genérica, sin importar con qué se
                // esté emparejando.
                if ($comboRule && $comboRule->from_membership_type_id === null) {
                    $siblingIsComboCapable = PricingRule::query()
                        ->where('membership_type_id', $sibling->membership_type_id)
                        ->where('requires_multiple_clubs', true)
                        ->exists();

                    if (!$siblingIsComboCapable) {
                        $comboRule = null;
                    }
                }

                $comboFee = $comboRule?->resolveMonthlyFee();

                if ($comboRule && $comboFee !== null) {
                    $pricingRuleId = $comboRule->id;
                    $interclubPackageRuleId = null;
                    $groupTotalMonthlyFee = $comboFee;
                    $representsGroupRate = true;
                }
            }
        }

        // synchronizeMembershipFees already resolves the full group context:
        // it finds all active memberships in the group, picks the max total fee,
        // and re-applies the split mode stored on the reactivated membership.
        $groupMemberships = $chargeService->synchronizeMembershipFees(
            membership: $reactivatedMembership,
            groupTotalMonthlyFee: $groupTotalMonthlyFee,
            historyReason: 'Recálculo de cuota: reactivación de cuenta en grupo',
            pricingRuleId: $pricingRuleId,
            interclubPackageRuleId: $interclubPackageRuleId
        );

        // synchronizeMembershipFees nunca apaga la facturación de las demás
        // membresías del grupo (a propósito, ver su comentario interno) — solo
        // prende la de la membresía reactivada. Si esta retoma una tarifa de
        // grupo (interclub), las demás deben volver a quedar no facturables,
        // igual que al dar de alta (MemberController::shouldSourceMembershipBecomeNonBillable).
        // Sin esto, tras reactivar quedan dos membresías cobrando a la vez.
        if ($representsGroupRate) {
            foreach ($groupMemberships as $groupMembership) {
                if (!$groupMembership->is($reactivatedMembership) && $groupMembership->is_billable) {
                    $groupMembership->update(['is_billable' => false]);
                }
            }
        }
    }

    /**
     * Paquete interclub específico para la combinación exacta de club+tipo
     * entre dos membresías activas, probando ambas direcciones porque
     * ninguna de las dos es necesariamente "la nueva" (a diferencia del alta
     * manual, donde siempre se sabe cuál es la membresía que se está
     * creando). Mismo criterio de columnas que
     * MemberController::resolveInterclubPackageRule /
     * MembershipImporter::resolveInterclubPackageRule.
     */
    public function resolveInterclubPackageRuleBetween(Membership $a, Membership $b): ?InterclubPackageRule
    {
        return $this->resolveInterclubPackageRuleDirectional(target: $a, source: $b)
            ?? $this->resolveInterclubPackageRuleDirectional(target: $b, source: $a);
    }

    protected function resolveInterclubPackageRuleDirectional(Membership $target, Membership $source): ?InterclubPackageRule
    {
        $today = now()->toDateString();

        return InterclubPackageRule::query()
            ->where('target_club_id', $target->club_id)
            ->where('target_membership_type_id', $target->membership_type_id)
            ->where('source_club_id', $source->club_id)
            ->where(function (Builder $query) use ($source) {
                $query->where('source_membership_type_id', $source->membership_type_id)
                    ->orWhereNull('source_membership_type_id');
            })
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
            ->whereNull('min_years_in_source_club')
            ->orderByRaw('CASE WHEN source_membership_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('priority')
            ->first();
    }

    public function resolvePricingRule(
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

    public function buildPricingRuleQuery(
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

    public function shouldApplyAgeFilter(MembershipType $membershipType): bool
    {
        return Str::contains((string) $membershipType->code, '_SOL');
    }
}
