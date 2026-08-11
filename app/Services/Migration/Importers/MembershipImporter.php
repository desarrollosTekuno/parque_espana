<?php

namespace App\Services\Migration\Importers;

use App\Models\Memberships\InterclubPackageRule;
use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\PricingRule;
use App\Services\Billing\MembershipPricingService;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MembershipImporter extends BaseImporter
{
    public function __construct(
        protected MembershipPricingService $pricingService
    ) {}

    public function name(): string
    {
        return 'Membresias';
    }

    public function sheetIndex(): int
    {
        return 2; // "2. Membresias"
    }

    public function import(
        Worksheet $sheet,
        MigrationContext $context,
        MigrationReport $report,
        bool $dryRun
    ): void {
        $ok      = 0;
        $errors  = [];
        $columns = $this->buildColumnMap($sheet);

        // Primera pasada: crear accounts y memberships.
        // Los GRUPO_FAMILIAR y CUENTA_ORIGEN se resuelven en una segunda pasada
        // porque pueden referenciar cuentas de filas posteriores.
        $deferred = []; // para resolver relaciones después

        foreach ($this->rows($sheet, $columns) as $rowNum => $row) {
            try {
                $noCuenta      = trim($row['NO_CUENTA'] ?? '');
                $tipoCode      = strtoupper(trim($row['TIPO_MEMBRESIA'] ?? ''));
                $parqueCode    = strtoupper(trim($row['PARQUE'] ?? ''));
                $fechaInicio   = $this->parseDate($row['FECHA_INICIO'] ?? null);
                $estatus       = strtolower(trim($row['ESTATUS'] ?? 'active'));
                $grupoFamiliar = trim($row['GRUPO_FAMILIAR'] ?? '');
                $cuentaOrigen  = trim($row['CUENTA_ORIGEN'] ?? '');

                if (empty($noCuenta) || empty($tipoCode) || empty($parqueCode)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'NO_CUENTA, TIPO_MEMBRESIA o PARQUE vacíos.'];
                    continue;
                }

                $clubId = $context->clubId($parqueCode);
                if (!$clubId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Parque '{$parqueCode}' no encontrado."];
                    continue;
                }

                $membershipTypeId = $context->membershipTypeId($tipoCode);
                if (!$membershipTypeId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Tipo de membresía '{$tipoCode}' no encontrado."];
                    continue;
                }

                // Determinar account_type según el código de tipo
                $accountType = str_contains(strtoupper($tipoCode), '_FAM') ? 'family' : 'individual';

                // Mapear estatus
                $accountStatus = match ($estatus) {
                    'activo', 'active'     => 'active',
                    'suspendido', 'suspended' => 'suspended',
                    'cancelado', 'inactivo' => 'cancelled',
                    default                => 'active',
                };

                // Obtener cuota mensual de la regla de precio (sin condiciones especiales)
                ['fee' => $monthlyFee, 'pricing_rule_id' => $pricingRuleId] = $this->resolveMonthlyFee($membershipTypeId);

                if (!$dryRun) {
                    $this->withSavepoint(function () use (
                        $noCuenta, $accountType, $accountStatus, $clubId,
                        $membershipTypeId, $monthlyFee, $pricingRuleId, $fechaInicio,
                        $grupoFamiliar, $cuentaOrigen, $context, &$deferred
                    ) {
                        $account = MembershipAccount::firstOrCreate(
                            ['membership_number' => $noCuenta],
                            [
                                'account_type' => $accountType,
                                'status'       => $accountStatus,
                                'club_id'      => $clubId,
                            ]
                        );

                        $membership = Membership::firstOrCreate(
                            [
                                'membership_account_id' => $account->id,
                                'club_id'               => $clubId,
                            ],
                            [
                                'membership_type_id'  => $membershipTypeId,
                                'is_primary'          => true,
                                'is_billable'         => true,
                                'monthly_fee'         => $monthlyFee,
                                'monthly_fee_total'   => $monthlyFee,
                                'monthly_fee_share'   => $monthlyFee,
                                'billing_split_mode'  => 'single',
                                'pricing_rule_id'     => $pricingRuleId,
                                'start_date'          => $fechaInicio ?? now()->toDateString(),
                                'status'              => $accountStatus,
                            ]
                        );

                        $context->accountsByNumber[$noCuenta] = $account->id;
                        $context->membershipByAccountAndClub["{$noCuenta}_{$clubId}"] = $membership->id;

                        $deferred[] = [
                            'account_id'     => $account->id,
                            'no_cuenta'      => $noCuenta,
                            'grupo_familiar' => $grupoFamiliar,
                            'cuenta_origen'  => $cuentaOrigen,
                        ];
                    });
                } else {
                    $context->accountsByNumber[$noCuenta] = 0;
                    $context->membershipByAccountAndClub["{$noCuenta}_{$clubId}"] = 0;
                }

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        // Segunda pasada: resolver GRUPO_FAMILIAR y CUENTA_ORIGEN
        if (!$dryRun) {
            foreach ($deferred as $item) {
                try {
                    $account = MembershipAccount::find($item['account_id']);
                    if (!$account) continue;

                    $dirty = false;

                    // GRUPO_FAMILIAR → account_group_id
                    if (!empty($item['grupo_familiar'])) {
                        $groupCode = $item['grupo_familiar'];

                        if (!isset($context->groupsByCode[$groupCode])) {
                            $group = MembershipAccountGroup::create(['status' => 'active']);
                            $context->groupsByCode[$groupCode] = $group->id;
                        }

                        $account->account_group_id = $context->groupsByCode[$groupCode];
                        $dirty = true;
                    }

                    // CUENTA_ORIGEN → origin_account_id
                    if (!empty($item['cuenta_origen'])) {
                        $originId = $context->accountId($item['cuenta_origen']);
                        if ($originId) {
                            $account->origin_account_id = $originId;
                            $dirty = true;
                        }
                    }

                    if ($dirty) {
                        $account->save();
                    }
                } catch (\Throwable) {
                    // No bloquear si falla la relación — solo se pierde el vínculo
                }
            }
        }

        // Tercera pasada: ajustar cuotas para grupos interclub.
        // En la primera pasada se guardó la tarifa standalone (hasMultipleClubs=false).
        // Ahora que los account_group_id ya están asignados, detectamos grupos que
        // abarcan más de un club y recalculamos con la tarifa interclub: la membresía
        // con la fecha de inicio más reciente absorbe la cuota completa
        // (billing_split_mode 'single'), igual que en el alta manual — ver
        // MemberController::resolveBillingSplitMode. El split 50/50 quedó
        // deshabilitado; las demás membresías del grupo solo se marcan no facturables.
        if (!$dryRun && !empty($context->groupsByCode)) {
            foreach (array_unique(array_values($context->groupsByCode)) as $groupId) {
                try {
                    $groupMemberships = Membership::query()
                        ->where('is_primary', true)
                        ->whereHas('account', fn ($q) => $q->where('account_group_id', $groupId))
                        ->orderByDesc('start_date')
                        ->orderByDesc('id')
                        ->get();

                    $uniqueClubs = $groupMemberships->pluck('club_id')->filter()->unique()->count();

                    if ($uniqueClubs <= 1 || $groupMemberships->count() < 2) {
                        continue;
                    }

                    // La membresía con la fecha de inicio más reciente es la que
                    // queda facturable; también sirve de referencia para resolver
                    // la tarifa interclub.
                    $billableMembership = $groupMemberships->first();
                    $sourceMembership = $groupMemberships->first(
                        fn (Membership $m) => $m->club_id !== $billableMembership->club_id
                    );

                    // La combinación real (p. ej. Individual en un parque +
                    // Familiar en el otro) puede tener su propia tarifa vía
                    // InterclubPackageRule (origen club+tipo → destino
                    // club+tipo) — igual que hace el alta manual, ver
                    // MemberController::resolveInterclubPackageRule. Si no
                    // hay un paquete específico para esta combinación, se
                    // cae a la regla genérica anterior (solo por el tipo de
                    // la membresía facturable, ignorando la del otro parque).
                    $interclubPackageRule = $sourceMembership
                        ? $this->resolveInterclubPackageRule($billableMembership, $sourceMembership)
                        : null;

                    if ($interclubPackageRule) {
                        $groupTotal = round((float) ($interclubPackageRule->resolveMonthlyFee() ?? 0), 2);
                        $interclubRuleId = $interclubPackageRule->id;
                        $genericPricingRuleId = null;
                    } else {
                        $interclubRule = $this->pricingService->resolvePricingRule(
                            membershipTypeId: $billableMembership->membership_type_id,
                            fromMembershipTypeId: null,
                            age: null,
                            hasMultipleClubs: true
                        );

                        if (!$interclubRule) {
                            continue;
                        }

                        $groupTotal = round((float) ($interclubRule->resolveMonthlyFee() ?? 0), 2);
                        $interclubRuleId = null;
                        $genericPricingRuleId = $interclubRule->id;
                    }

                    foreach ($groupMemberships as $membership) {
                        if ($membership->is($billableMembership)) {
                            $membership->update([
                                'monthly_fee'               => $groupTotal,
                                'monthly_fee_total'         => $groupTotal,
                                'monthly_fee_share'         => $groupTotal,
                                'billing_split_mode'        => 'single',
                                'is_billable'               => true,
                                'pricing_rule_id'           => $genericPricingRuleId,
                                'interclub_package_rule_id' => $interclubRuleId,
                            ]);
                        } else {
                            $membership->update([
                                'billing_split_mode' => 'single',
                                'is_billable'        => false,
                            ]);
                        }
                    }
                } catch (\Throwable) {
                    // No bloquear si falla el ajuste de un grupo
                }
            }
        }

        $report->record($this->name(), $ok, $errors);
    }

    /**
     * Busca el paquete interclub que corresponde EXACTAMENTE a la combinación
     * origen→destino del grupo (mismo criterio que
     * MemberController::resolveInterclubPackageRule para el alta manual):
     * parque+tipo de la membresía que se vuelve no facturable (origen) hacia
     * parque+tipo de la que se queda facturable (destino). Null si no hay
     * ningún paquete capturado para esa combinación específica — en ese caso
     * el llamador cae a la regla genérica por tipo (ver import()).
     */
    private function resolveInterclubPackageRule(
        Membership $billableMembership,
        Membership $sourceMembership
    ): ?InterclubPackageRule {
        $today = now()->toDateString();

        return InterclubPackageRule::query()
            ->where('target_club_id', $billableMembership->club_id)
            ->where('target_membership_type_id', $billableMembership->membership_type_id)
            ->where('source_club_id', $sourceMembership->club_id)
            ->where(function (Builder $query) use ($sourceMembership) {
                $query->where('source_membership_type_id', $sourceMembership->membership_type_id)
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

    /**
     * @return array{fee: float, pricing_rule_id: ?int}
     */
    private function resolveMonthlyFee(int $membershipTypeId): array
    {
        $rule = $this->pricingService->resolvePricingRule(
            membershipTypeId: $membershipTypeId,
            fromMembershipTypeId: null,
            age: null,
            hasMultipleClubs: false
        );

        if (!$rule) {
            // Tipos con reglas solo por rango de edad (ej. solidaria) no tienen una
            // regla con min_age/max_age NULL, así que el intento anterior no encuentra nada.
            // Tomamos la primera regla activa ignorando el rango de edad.
            $today = now()->toDateString();
            $rule = PricingRule::where('membership_type_id', $membershipTypeId)
                ->where('is_active', true)
                ->whereNull('from_membership_type_id')
                ->where('requires_multiple_clubs', false)
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                ->orderBy('priority')
                ->first();
        }

        return [
            'fee'             => $rule ? (float) ($rule->resolveMonthlyFee() ?? 0) : 0.00,
            'pricing_rule_id' => $rule?->id,
        ];
    }
}
