<?php

namespace App\Services\Migration\Importers;

use App\Models\Members\Member;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserImporter extends BaseImporter
{
    public function name(): string
    {
        return 'Usuarios';
    }

    public function sheetIndex(): int
    {
        return 1; // "1. Usuarios"
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
                $originId = $row['ID_ORIGEN'] ?? null;

                if (empty($originId)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'ID_ORIGEN vacío, fila omitida.'];
                    continue;
                }

                $email = !empty($row['CORREO']) ? strtolower(trim($row['CORREO'])) : null;

                if (!$dryRun) {
                    $this->withSavepoint(function () use ($originId, $email, $row, $context) {
                        $member = Member::updateOrCreate(
                            ['migration_origin_id' => $originId],
                            [
                                'first_name'          => $row['NOMBRE'] ?? '',
                                'last_name'           => $row['APELLIDO_PATERNO'] ?? '',
                                'second_last_name'    => $row['APELLIDO_MATERNO'] ?? null,
                                'birthdate'           => $this->parseDate($row['FECHA_NACIMIENTO'] ?? null),
                                'phone'               => $row['TELEFONO'] ?? null,
                                'email'               => $email,
                                'nationality'         => $row['NACIONALIDAD'] ?? null,
                                'marital_status'      => $row['ESTADO_CIVIL'] ?? null,
                                'occupation'          => $row['OCUPACION'] ?? null,
                                'user_id'             => null,
                                'gender'              => $row['GENERO'] ?? null,
                            ]
                        );
                        $context->membersByOriginId[$originId] = $member->id;
                    });
                } else {
                    // En dry-run usamos un ID ficticio para que los importadores siguientes
                    // puedan continuar con la simulación
                    $context->membersByOriginId[$originId] = 0;
                }

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        $report->record($this->name(), $ok, $errors);
    }
}
