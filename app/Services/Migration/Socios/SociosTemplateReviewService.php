<?php

namespace App\Services\Migration\Socios;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SociosTemplateReviewService
{
    public function review(string $file): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Personal', 'Usuarios', 'Membresias', 'Integrantes']);
        $book = $reader->load($file);

        try {
            $data = [];
            foreach (['Personal', 'Usuarios', 'Membresias', 'Integrantes'] as $name) {
                if (!$book->sheetNameExists($name)) {
                    throw new RuntimeException("Falta la pestaña {$name}.");
                }
                $sheet = $book->getSheetByName($name);
                $headers = [];
                foreach ($sheet->getRowIterator(2, 2)->current()->getCellIterator() as $cell) {
                    $header = trim((string) $cell->getValue());
                    if ($header !== '') {
                        $headers[$header] = $cell->getColumn();
                    }
                }
                $required = match ($name) {
                    'Personal' => ['NOMBRE', 'CORREO', 'ROL', 'CLUBES', 'SERIE DE CAJA'],
                    'Usuarios' => ['ID DE USUARIO', 'FECHA DE NACIMIENTO', 'TELEFONO', 'CORREO', 'OCUPACION', 'CALLE Y NUMERO', 'COLONIA', 'CODIGO POSTAL', 'PAIS', 'ESTADO', 'CIUDAD', 'AÑOS EN LA CIUDAD'],
                    'Membresias' => ['NUMERO DE CUENTA', 'ESTATUS', 'CUOTA MENSUAL', 'GENERA COBRO', 'CUENTA EN EL OTRO PARQUE'],
                    'Integrantes' => ['NUMERO DE CUENTA', 'ID DE USUARIO', 'ES TITULAR'],
                };
                foreach ($required as $header) {
                    if (!isset($headers[$header])) {
                        throw new RuntimeException("{$name}: falta la columna {$header}.");
                    }
                }
                $data[$name] = [];
                for ($number = 4; $number <= $sheet->getHighestDataRow(); $number++) {
                    $row = ['_row' => $number];
                    foreach ($headers as $header => $column) {
                        $row[$header] = trim((string) ($sheet->getCell("{$column}{$number}")->getCalculatedValue() ?? ''));
                    }
                    if (collect($row)->except('_row')->contains(fn ($value) => $value !== '')) {
                        $data[$name][] = $row;
                    }
                }
            }

            $errors = [];
            $holderIds = collect($data['Integrantes'])
                ->filter(fn ($row) => mb_strtoupper($row['ES TITULAR'] ?? '') === 'SI')
                ->pluck('ID DE USUARIO')
                ->all();

            foreach ($data['Membresias'] as $row) {
                $bill = mb_strtoupper($row['GENERA COBRO'] ?? '');
                $fee = $row['CUOTA MENSUAL'] ?? '';
                if (!in_array($bill, ['SI', 'NO'], true)) {
                    $errors[] = $this->issue('Membresias', $row['_row'], 'GENERA COBRO', 'Este campo es obligatorio. Escriba SI o NO.', 'Excel incompleto');
                }
                if ($bill === 'SI' && ($fee === '' || !is_numeric($fee) || (float) $fee <= 0)) {
                    $errors[] = $this->issue('Membresias', $row['_row'], 'CUOTA MENSUAL', 'Cuando GENERA COBRO es SI, este campo es obligatorio y debe ser mayor que cero.', 'Excel incompleto');
                }
                if ($bill === 'NO' && $fee !== '' && (!is_numeric($fee) || (float) $fee != 0)) {
                    $errors[] = $this->issue('Membresias', $row['_row'], 'CUOTA MENSUAL', 'Cuando GENERA COBRO es NO, la cuota debe estar vacía o en cero.', 'Excel inconsistente');
                }
            }

            foreach ($data['Usuarios'] as $row) {
                if (($row['CORREO'] ?? '') === '') {
                    $message = in_array($row['ID DE USUARIO'], $holderIds, true)
                        ? 'El correo del titular es obligatorio para cargar la cuenta y darle acceso a la app.'
                        : 'El correo del usuario es obligatorio para esta carga.';
                    $errors[] = $this->issue('Usuarios', $row['_row'], 'CORREO', $message, 'Excel incompleto');
                }
                if (($row['FECHA DE NACIMIENTO'] ?? '') === '') {
                    $errors[] = $this->issue('Usuarios', $row['_row'], 'FECHA DE NACIMIENTO', 'Se necesita para la edad y las reglas de membresía.', 'Excel incompleto');
                }
            }

            return [
                'counts' => collect($data)->map(fn ($rows) => count($rows))->all(),
                'errors' => $errors,
            ];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function issue(string $sheet, ?int $row, string $field, string $message, string $origin): array
    {
        return compact('sheet', 'row', 'field', 'message', 'origin');
    }
}
