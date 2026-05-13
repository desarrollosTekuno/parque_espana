<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CancellationHistoryExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected Collection $records) {}

    public function title(): string
    {
        return 'Historial de bajas';
    }

    public function headings(): array
    {
        return [
            'No. Socio',
            'Titular',
            'Correo',
            'Tipo de membresía',
            'Tipo de baja',
            'Motivo',
            'Fecha de baja',
            'Procesada por',
        ];
    }

    public function collection(): Collection
    {
        return $this->records->map(fn (array $row) => [
            $row['membership_number'],
            $row['holder_name'],
            $row['email'],
            $row['membership_type_name'],
            $this->typeLabel($row['cancellation_type']),
            $this->motivoLabel($row['cancellation_type']),
            $row['cancelled_at'] ? Carbon::parse($row['cancelled_at'])->format('d/m/Y H:i') : '',
            $row['cancelled_by_name'],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            'voluntary' => 'Voluntaria',
            'sanction'  => 'Sanción',
            default     => $type ?? '-',
        };
    }

    private function motivoLabel(?string $type): string
    {
        return match ($type) {
            'voluntary' => 'Solicitud voluntaria del titular',
            'sanction'  => 'Baja por sanción disciplinaria',
            default     => '-',
        };
    }
}
