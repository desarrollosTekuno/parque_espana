<?php

namespace App\Services\Migration\Importers;

use App\Models\Catalogs\Relationship;
use App\Models\Memberships\MembershipAccountMember;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IntegranteImporter extends BaseImporter
{
    public function name(): string
    {
        return 'Integrantes';
    }

    public function sheetIndex(): int
    {
        return 3; // "3. Integrantes"
    }

    public function import(
        Worksheet $sheet,
        MigrationContext $context,
        MigrationReport $report,
        bool $dryRun
    ): void {
        $ok             = 0;
        $errors         = [];
        $columns        = $this->buildColumnMap($sheet);
        $relationships  = $this->loadRelationships();

        foreach ($this->rows($sheet, $columns) as $rowNum => $row) {
            try {
                $noCuenta   = trim($row['NO_CUENTA'] ?? '');
                $originId   = trim($row['ID_ORIGEN'] ?? '');
                $esTitular  = $this->parseBool($row['ES_TITULAR'] ?? null);
                $parentesco = strtolower(trim($row['PARENTESCO'] ?? ''));

                if (empty($noCuenta) || empty($originId)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'NO_CUENTA o ID_ORIGEN vacíos.'];
                    continue;
                }

                $accountId = $context->accountId($noCuenta);
                if (!$accountId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Cuenta '{$noCuenta}' no encontrada. ¿Corrió el importador de Membresias?"];
                    continue;
                }

                $memberId = $context->memberId($originId);
                if (!$memberId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Usuario '{$originId}' no encontrado. ¿Corrió el importador de Usuarios?"];
                    continue;
                }

                $relationshipId = $relationships[$parentesco] ?? null;

                if (!$dryRun) {
                    $this->withSavepoint(function () use (
                        $accountId, $memberId, $relationshipId, $esTitular, $noCuenta, $context
                    ) {
                        MembershipAccountMember::updateOrCreate(
                            [
                                'membership_account_id' => $accountId,
                                'member_id'             => $memberId,
                            ],
                            [
                                'relationship_id'   => $relationshipId,
                                'is_primary_holder' => $esTitular,
                            ]
                        );

                        if ($esTitular) {
                            $context->primaryHolderByAccount[$noCuenta] = $memberId;
                        }
                    });
                }

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        $report->record($this->name(), $ok, $errors);
    }

    private function loadRelationships(): array
    {
        return Relationship::all()
            ->mapWithKeys(fn ($r) => [strtolower($r->name) => $r->id])
            ->toArray();
    }
}
