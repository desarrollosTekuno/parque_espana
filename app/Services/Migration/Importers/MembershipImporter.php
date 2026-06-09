<?php

namespace App\Services\Migration\Importers;

use App\Models\Memberships\Membership;
use App\Models\Memberships\MembershipAccount;
use App\Models\Memberships\MembershipAccountGroup;
use App\Models\Memberships\PricingRule;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MembershipImporter extends BaseImporter
{
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
                    'activa', 'active'     => 'active',
                    'suspendida'           => 'suspended',
                    'cancelada'            => 'cancelled',
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
                        $account = MembershipAccount::updateOrCreate(
                            ['membership_number' => $noCuenta],
                            [
                                'account_type' => $accountType,
                                'status'       => $accountStatus,
                                'club_id'      => $clubId,
                            ]
                        );

                        $membership = Membership::updateOrCreate(
                            [
                                'membership_account_id' => $account->id,
                                'club_id'               => $clubId,
                            ],
                            [
                                'membership_type_id' => $membershipTypeId,
                                'is_primary'         => true,
                                'monthly_fee'        => $monthlyFee,
                                'start_date'         => $fechaInicio ?? now()->toDateString(),
                                'status'             => $accountStatus,
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

        $report->record($this->name(), $ok, $errors);
    }

    private function resolveMonthlyFee(int $membershipTypeId): float
    {
        $rule = PricingRule::where('membership_type_id', $membershipTypeId)
            ->whereNull('from_membership_type_id')
            ->whereNull('min_age')
            ->where('requires_multiple_clubs', false)
            ->orderByDesc('priority')
            ->first();

        return $rule ? (float) $rule->monthly_fee : 0.00;
    }
}
