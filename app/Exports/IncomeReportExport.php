<?php

namespace App\Exports;

use App\Models\Billing\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class IncomeReportExport implements FromArray, WithEvents, WithTitle {
    public function __construct( protected string $clubName, protected Carbon $startDate, protected Carbon $endDate, protected Collection $payments, protected string $deliveredBy, protected ?string $logoContent = null) {}

    public function title(): string {
        return 'Ingresos';
    }

    public function array(): array {
        $title = "Resumen Administrativo de ingresos cobrados en Deportivo {$this->clubName}";
        $period = 'Reporte del '.$this->startDate->format('d/m/y').' al '.$this->endDate->format('d/m/y');
        $rows = [
            array_fill(0, 12, null),
            [null, null, null, $title, null, null, null, null, null, null, null, null],
            array_fill(0, 12, null),
            [null, null, null, null, null, null, null, null, null, $period, null, null],
            array_fill(0, 12, null),
            [
                'Día',
                'TOTAL',
                'Fecha Depósito',
                'Efectivo',
                'Tarjeta de Crédito',
                'Tarjeta de Débito',
                'Cheque Nominativo',
                'Transferencia',
                'Cargo Automático',
                'Internet App Crédito',
                'Internet App Débito',
                'Internet App Transferencia',
            ],
        ];

        $date = $this->startDate->copy()->startOfDay();
        $endDate = $this->endDate->copy()->startOfDay();

        $paymentTotals = array_fill(0, 9, 0.0);
        $paymentsByDate = $this->payments->groupBy(
            fn (Payment $payment) => $payment->paid_at->copy()->setTimezone(config('app.timezone'))->toDateString()
        );

        while ($date->lte($endDate)) {
            $amounts = array_fill(0, 9, 0.0);
            $dayPayments = $paymentsByDate->get($date->toDateString(), collect());

            foreach ($dayPayments as $payment) {
                $column = $this->paymentColumn($payment);

                if ($column !== null) {
                    $amounts[$column] += (float) $payment->amount;
                    $paymentTotals[$column] += (float) $payment->amount;
                }
            }

            $rows[] = [Date::PHPToExcel($date), null, null, ...$amounts];
            $date->addDay();
        }

        $rows[] = ['TOTAL', null, null, ...$paymentTotals];
        $rows[] = array_fill(0, 12, null);
        $rows[] = array_fill(0, 12, null);
        $rows[] = [null, 'Notas y Observaciones', null, null, null, null, null, null, null, null, null, null];
        $rows[] = array_fill(0, 12, null);
        $rows[] = array_fill(0, 12, null);
        $rows[] = array_fill(0, 12, null);
        $rows[] = [null, null, 'ENTREGA', null, null, null, null, 'REVISA', null, null, null, null];
        $rows[] = array_fill(0, 12, null);
        $rows[] = [null, null, $this->deliveredBy, null, null, null, null, 'Lic. Veronica De La Rosa Nava', null, null, null, null];
        $rows[] = [null, null, null, null, null, null, null, 'Gerente Administrativo', null, null, null, null];

        return $rows;
    }

    public function registerEvents(): array {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $days = $this->startDate->copy()->startOfDay()->diffInDays($this->endDate->copy()->startOfDay()) + 1;
                $lastDateRow = 6 + $days;
                $totalRow = $lastDateRow + 1;
                $notesRow = $totalRow + 3;
                $signatureLabelRow = $totalRow + 7;
                $signatureNameRow = $totalRow + 9;
                $signaturePositionRow = $totalRow + 10;

                $sheet->mergeCells('D2:J2');
                $sheet->mergeCells('J4:L4');

                $sheet->getStyle('D2')->getFont()
                    ->setBold(true)
                    ->setSize(16);
                $sheet->getStyle('D2')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('J4:L4')->getFont()
                    ->setBold(true)
                    ->setSize(12);
                $sheet->getStyle('J4:L4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('J4:L4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFFFFF00');

                $sheet->getRowDimension(1)->setRowHeight(18);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(18);
                $sheet->getRowDimension(4)->setRowHeight(22);
                $sheet->getRowDimension(6)->setRowHeight(42);

                $sheet->getStyle('A6:L6')->getFont()->setBold(false);
                $sheet->getStyle('A6:L6')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $tableBorder = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ];
                $sheet->getStyle("A6:L{$totalRow}")->applyFromArray($tableBorder);

                $sheet->getStyle("A7:A{$lastDateRow}")
                    ->getNumberFormat()
                    ->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle("A7:C{$lastDateRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("D7:L{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"$"#,##0.00;[Red]-"$"#,##0.00;"$"-');

                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getFont()
                    ->setBold(true)
                    ->setItalic(true);

                $sheet->mergeCells("B{$notesRow}:C{$notesRow}");
                $sheet->mergeCells("C{$signatureLabelRow}:E{$signatureLabelRow}");
                $sheet->mergeCells("H{$signatureLabelRow}:J{$signatureLabelRow}");
                $sheet->mergeCells("C{$signatureNameRow}:E{$signatureNameRow}");
                $sheet->mergeCells("H{$signatureNameRow}:J{$signatureNameRow}");
                $sheet->mergeCells("C{$signaturePositionRow}:E{$signaturePositionRow}");
                $sheet->mergeCells("H{$signaturePositionRow}:J{$signaturePositionRow}");

                $sheet->getStyle("C{$signatureLabelRow}:J{$signaturePositionRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("C{$signatureLabelRow}:J{$signatureLabelRow}")->getFont()
                    ->setBold(true);

                $sheet->getPageSetup()->setPrintArea("A1:L{$signaturePositionRow}");

                $sheet->getColumnDimension('A')->setWidth(13);
                $sheet->getColumnDimension('B')->setWidth(16);
                $sheet->getColumnDimension('C')->setWidth(14);
                foreach (range('D', 'L') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(17);
                }

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1);

                $this->addLogo($sheet);
            },
        ];
    }

    private function paymentColumn(Payment $payment): ?int {
        $code = strtoupper((string) $payment->paymentMethod?->code);

        if ($code === 'APP_PAYMENT') {
            $appPaymentType = strtolower((string) ($payment->metadata['app_payment_type'] ?? 'credit'));

            return match ($appPaymentType) {
                'debit' => 7,
                'transfer', 'spei' => 8,
                default => 6,
            };
        }

        return match ($code) {
            'CASH' => 0,
            'CREDIT_CARD' => 1,
            'DEBIT_CARD' => 2,
            'CHECK' => 3,
            'BANK_TRANSFER' => 4,
            'AUTOMATIC_CHARGE', 'AUTOMATIC_DEBIT' => 5,
            'SPEI' => 8,
            default => null,
        };
    }

    private function addLogo($sheet): void {
        if (! $this->logoContent) {
            return;
        }

        $image = imagecreatefromstring($this->logoContent);
        $imageInfo = getimagesizefromstring($this->logoContent);

        if (! $image || ! $imageInfo) {
            return;
        }

        $drawing = new MemoryDrawing;
        $drawing->setName('Logo del club');
        $drawing->setDescription('Logo del club activo');
        $drawing->setImageResource($image);
        $drawing->setHeight(72);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(2);

        if ($imageInfo['mime'] === 'image/png') {
            $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
            $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
        } elseif ($imageInfo['mime'] === 'image/gif') {
            $drawing->setRenderingFunction(MemoryDrawing::RENDERING_GIF);
            $drawing->setMimeType(MemoryDrawing::MIMETYPE_GIF);
        } else {
            $drawing->setRenderingFunction(MemoryDrawing::RENDERING_JPEG);
            $drawing->setMimeType(MemoryDrawing::MIMETYPE_JPEG);
        }

        $drawing->setWorksheet($sheet);
    }
}
