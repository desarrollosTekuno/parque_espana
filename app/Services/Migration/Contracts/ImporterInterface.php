<?php

namespace App\Services\Migration\Contracts;

use App\Services\Migration\MigrationContext;
use App\Services\Migration\MigrationReport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

interface ImporterInterface
{
    /** Nombre legible del importador (aparece en el reporte). */
    public function name(): string;

    /** Índice de hoja (0-based) en el Excel que este importador procesa. */
    public function sheetIndex(): int;

    /**
     * Ejecuta la importación de la hoja.
     *
     * @param  Worksheet        $sheet    Hoja ya cargada del spreadsheet
     * @param  MigrationContext $context  Contexto compartido (se modifica in-place)
     * @param  MigrationReport  $report   Acumulador de resultados
     * @param  bool             $dryRun   Si true, no persistir nada en BD
     */
    public function import(
        Worksheet $sheet,
        MigrationContext $context,
        MigrationReport $report,
        bool $dryRun
    ): void;
}
