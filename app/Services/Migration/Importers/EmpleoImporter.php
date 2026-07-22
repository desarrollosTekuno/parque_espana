<?php

namespace App\Services\Migration\Importers;

use App\Models\Members\EmploymentInfo;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmpleoImporter extends BaseImporter
{
    public function name(): string
    {
        return 'Empleo';
    }

    public function sheetIndex(): int
    {
        return 5; // "5. Empleo"
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
                        EmploymentInfo::updateOrCreate(
                            ['member_id' => $memberId],
                            [
                                'company_name'    => $row['EMPRESA'] ?? null,
                                'company_address' => $row['DIRECCION_EMPRESA'] ?? null,
                                'company_phone'   => $row['TELEFONO_EMPRESA'] ?? null,
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
