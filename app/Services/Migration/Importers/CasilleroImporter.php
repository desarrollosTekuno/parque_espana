<?php

namespace App\Services\Migration\Importers;

use App\Models\Members\Locker;
use App\Models\Members\LockerAssignment;
use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CasilleroImporter extends BaseImporter
{
    public function name(): string
    {
        return 'Casilleros';
    }

    public function sheetIndex(): int
    {
        return 8; // "9. Casilleros"
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
                $noCuenta        = trim($row['NO_CUENTA'] ?? '');
                $parqueCode      = strtoupper(trim($row['PARQUE'] ?? ''));
                $numeroCasillero = trim($row['NUMERO_CASILLERO'] ?? '');
                $año             = $this->parseInt($row['AÑO'] ?? null);

                if (empty($noCuenta) || empty($parqueCode) || empty($numeroCasillero) || !$año) {
                    $errors[] = ['row' => $rowNum, 'message' => 'NO_CUENTA, PARQUE, NUMERO_CASILLERO o AÑO vacíos.'];
                    continue;
                }

                $clubId = $context->clubId($parqueCode);
                if (!$clubId) {
                    $errors[] = ['row' => $rowNum, 'message' => "Parque '{$parqueCode}' no encontrado."];
                    continue;
                }

                // Obtener member_id del titular de la cuenta
                $memberId = $context->primaryHolderByAccount[$noCuenta] ?? null;
                if (!$memberId) {
                    $errors[] = ['row' => $rowNum, 'message' => "No se encontró titular para la cuenta '{$noCuenta}'."];
                    continue;
                }

                $startDate = $this->parseDate($row['FECHA_INICIO'] ?? null);
                $endDate   = $this->parseDate($row['FECHA_FIN'] ?? null);
                $estatus   = strtolower(trim($row['ESTATUS'] ?? 'asignado'));

                // Estado del locker basado en el estatus del registro
                $lockerStatus = match ($estatus) {
                    'asignado'        => 'ocupado',
                    'vencido'         => 'disponible', // ya expiró, se libera
                    'libre'           => 'disponible',
                    default           => 'ocupado',
                };

                if (!$dryRun) {
                    // Buscar o crear el locker por número y club
                    $locker = Locker::firstOrCreate(
                        ['club_id' => $clubId, 'number' => $numeroCasillero],
                        ['status' => $lockerStatus, 'category' => 'general']
                    );

                    // Si el registro es el más reciente (vigente), actualizar status del locker
                    if ($estatus === 'asignado') {
                        $locker->update(['status' => 'ocupado']);
                    }

                    // Crear asignación
                    LockerAssignment::updateOrCreate(
                        [
                            'locker_id' => $locker->id,
                            'member_id' => $memberId,
                            'year'      => $año,
                        ],
                        [
                            'club_id'    => $clubId,
                            'start_date' => $startDate,
                            'end_date'   => $endDate,
                            'amount_paid' => 0, // El pago se registra en HistorialImporter
                        ]
                    );
                }

                $ok++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        $report->record($this->name(), $ok, $errors);
    }
}
