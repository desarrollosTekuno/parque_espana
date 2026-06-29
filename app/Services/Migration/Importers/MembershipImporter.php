<?php

namespace App\Services\Migration\Importers;

use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\PricingRule;
use App\Services\Billing\MembershipPricingService;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
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
                $monthlyFee = $this->resolveMonthlyFee($membershipTypeId);

                if (!$dryRun) {
                    $this->withSavepoint(function () use (
                        $noCuenta, $accountType, $accountStatus, $clubId,
                        $membershipTypeId, $monthlyFee, $fechaInicio,
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

        // Tercera pasada: ajustar cuotas para grupos interclub (equal_split).
        // En la primera pasada se guardó la tarifa standalone (hasMultipleClubs=false).
        // Ahora que los account_group_id ya están asignados, detectamos grupos que
        // abarcan más de un club y recalculamos con la tarifa interclub.
        if (!$dryRun && !empty($context->groupsByCode)) {
            foreach (array_unique(array_values($context->groupsByCode)) as $groupId) {
                try {
                    $groupMemberships = Membership::query()
                        ->where('is_primary', true)
                        ->whereHas('account', fn ($q) => $q->where('account_group_id', $groupId))
                        ->orderBy('club_id')
                        ->orderBy('id')
                        ->get();

                    $uniqueClubs = $groupMemberships->pluck('club_id')->filter()->unique()->count();

                    if ($uniqueClubs <= 1 || $groupMemberships->count() < 2) {
                        continue;
                    }

                    // Resolver tarifa interclub para el tipo de membresía del primer registro
                    $representative = $groupMemberships->first();
                    $interclubRule = $this->pricingService->resolvePricingRule(
                        membershipTypeId: $representative->membership_type_id,
                        fromMembershipTypeId: null,
                        age: null,
                        hasMultipleClubs: true
                    );

                    if (!$interclubRule) {
                        continue;
                    }

                    $groupTotal   = round((float) $interclubRule->monthly_fee, 2);
                    $count        = $groupMemberships->count();
                    $splitAmount  = round($groupTotal / $count, 2);
                    $allocated    = 0.0;
                    $last         = $groupMemberships->last();

                    foreach ($groupMemberships as $membership) {
                        $share = $membership->is($last)
                            ? round($groupTotal - $allocated, 2)
                            : $splitAmount;

                        $allocated = round($allocated + $share, 2);

                        $membership->update([
                            'monthly_fee'        => $groupTotal,
                            'monthly_fee_total'  => $groupTotal,
                            'monthly_fee_share'  => $share,
                            'billing_split_mode' => 'equal_split',
                            'is_billable'        => $share > 0,
                        ]);
                    }
                } catch (\Throwable) {
                    // No bloquear si falla el ajuste de un grupo
                }
            }
        }

        $report->record($this->name(), $ok, $errors);
    }

    private function resolveMonthlyFee(int $membershipTypeId): float
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

        return $rule ? (float) $rule->monthly_fee : 0.00;
    }
}
