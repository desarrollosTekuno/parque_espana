<?php

namespace App\Services\Migration\Importers;

use App\Models\Members\Address;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DomicilioImporter extends BaseImporter
{
    public function name(): string
    {
        return 'Domicilios';
    }

    public function sheetIndex(): int
    {
        return 4; // "4. Domicilios"
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

        foreach ($this->rows($sheet, $columns) as $rowNum => $row) {
            try {
                $originId = trim($row['ID_ORIGEN'] ?? '');

                if (empty($originId)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'ID_ORIGEN vacío.'];
                    continue;
                }

                $memberId = $context->memberId($originId);
                if (!$memberId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Usuario '{$originId}' no encontrado."];
                    continue;
                }

                if (!$dryRun) {
                    $this->withSavepoint(function () use ($memberId, $row) {
                        Address::updateOrCreate(
                            ['member_id' => $memberId, 'is_primary' => true],
                            [
                                'street'        => $row['CALLE'] ?? null,
                                'neighborhood'  => $row['COLONIA'] ?? null,
                                'postal_code'   => $row['CODIGO_POSTAL'] ?? null,
                                'city'          => $row['CIUDAD'] ?? null,
                                'state'         => $row['ESTADO'] ?? null,
                                'country'       => $row['PAIS'] ?? 'México',
                                'years_in_city' => $this->parseInt($row['ANOS_RADICANDO'] ?? null),
                                'is_primary'    => true,
                            ]
                        );
                    });
                }

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        $report->record($this->name(), $ok, $errors);
    }
}
