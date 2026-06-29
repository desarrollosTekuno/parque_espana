<?php

namespace App\Services\Migration\Importers;

use App\Services\Migration\Contracts\ImporterInterface;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseImporter implements ImporterInterface
{
    /**
     * Ejecuta $callback dentro de un savepoint (transacción anidada en PostgreSQL).
     * Si el callback lanza una excepción, hace rollback solo al savepoint
     * y NO aborta la transacción principal — crítico para PostgreSQL.
     */
    protected function withSavepoint(callable $callback): void
    {
        \Illuminate\Support\Facades\DB::transaction($callback);
    }
    protected const HEADER_ROW = 2;
    protected const DATA_START  = 4; // fila 3 = hints, fila 4 = primer dato

    // ── Utilidades de lectura ───────────────────────────────────────────────────

    /**
     * Construye un mapa [ 'NOMBRE_COLUMNA' => índice_columna (int) ]
     * leyendo la fila de encabezados.
     */
    protected function buildColumnMap(Worksheet $sheet, int $headerRow = self::HEADER_ROW): array
    {
        $map      = [];
        $maxCol   = $sheet->getHighestDataColumn($headerRow);
        $maxColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);

        for ($col = 1; $col <= $maxColIdx; $col++) {
            $value = $sheet->getCellByColumnAndRow($col, $headerRow)->getValue();

            if ($value !== null && $value !== '') {
                $map[strtoupper(trim((string) $value))] = $col;
            }
        }

        return $map;
    }

    /**
     * Itera filas de datos y retorna arrays asociativos
     * [ 'COLUMNA' => valor ].
     *
     * @return \Generator<int, array>  key = número de fila Excel
     */
    protected function rows(Worksheet $sheet, array $columnMap, int $dataStart = self::DATA_START): \Generator
    {
        $maxRow = $sheet->getHighestDataRow();

        for ($row = $dataStart; $row <= $maxRow; $row++) {
            $data = [];

            foreach ($columnMap as $header => $colIdx) {
                $cell  = $sheet->getCellByColumnAndRow($colIdx, $row);
                $value = $cell->getValue();

                // Si la celda tiene formato fecha de Excel, obtener valor formateado
                if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($value)) {
                    $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                        ->format('Y-m-d');
                }

                $data[$header] = $value !== null ? trim((string) $value) : null;
            }

            if ($this->isEmpty($data)) {
                continue;
            }

            yield $row => $data;
        }
    }

    /**
     * Considera la fila vacía si todos sus valores son null o ''.
     */
    protected function isEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Convierte un valor de fecha del Excel a formato Y-m-d o null.
     */
    protected function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Ya viene en Y-m-d desde rows()
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Formato D/M/Y o D-M-Y
        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $value)?->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseDecimal(?string $value): ?float
    {
        if (empty($value)) {
            return null;
        }

        $clean = str_replace([',', ' '], '', $value);
        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function parseInt(?string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function parseBool(?string $value): bool
    {
        return in_array(strtoupper(trim($value ?? '')), ['SI', 'SÍ', 'TRUE', '1', 'YES']);
    }
}
