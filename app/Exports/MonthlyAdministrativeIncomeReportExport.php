<?php

namespace App\Exports;

use App\Models\Billing\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class MonthlyAdministrativeIncomeReportExport implements FromArray, WithEvents, WithTitle
{
    public function __construct(
        protected string $clubName,
        protected Carbon $startDate,
        protected Carbon $endDate,
        protected Collection $payments,
        protected string $deliveredBy,
    ) {
    }

    public function title(): string
    {
        return 'Resumen mensual';
    }

    public function array(): array
    {
        $rows = [
            [null, null, null],
            ["Resumen Administrativo Mensual de ingresos cobrados en Deportivo {$this->clubName}", null, null],
            [null, null, null],
            ['Reporte del '.$this->startDate->format('d/m/y').' al '.$this->endDate->format('d/m/y'), null, null],
            ['Día', 'TOTAL', 'Fecha Depósito'],
        ];

        $paymentsByDate = $this->payments->groupBy(
            fn (Payment $payment) => $payment->paid_at
                ->copy()
                ->setTimezone(config('app.timezone'))
                ->toDateString()
        );

        $date = $this->startDate->copy()->startOfDay();
        $endDate = $this->endDate->copy()->startOfDay();

        while ($date->lte($endDate)) {
            $dayPayments = $paymentsByDate->get($date->toDateString(), collect());

            $rows[] = [
                Date::PHPToExcel($date),
                round((float) $dayPayments->sum('amount'), 2),
                null,
            ];

            $date->addDay();
        }

        $rows[] = ['TOTAL', round((float) $this->payments->sum('amount'), 2), null];
        $rows[] = [null, null, null];
        $rows[] = ['Notas y Observaciones', null, null];
        $rows[] = [null, null, null];
        $rows[] = [null, null, null];
        $rows[] = ['ENTREGA', null, 'RECIBE'];
        $rows[] = [$this->deliveredBy, null, null];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $days = $this->startDate->copy()->startOfDay()
                    ->diffInDays($this->endDate->copy()->startOfDay()) + 1;
                $lastDateRow = 5 + $days;
                $totalRow = $lastDateRow + 1;
                $notesRow = $totalRow + 2;
                $signatureRow = $totalRow + 5;
                $signatureNameRow = $signatureRow + 1;

                $sheet->mergeCells('A2:J2');
                $sheet->mergeCells('A4:C4');
                $sheet->mergeCells("A{$notesRow}:C{$notesRow}");

                $sheet->getStyle('A2')->getFont()->setBold(true)->setItalic(true)->setSize(14);
                $sheet->getStyle('A4')->getFont()->setBold(true)->setItalic(true);
                $sheet->getStyle('A2:J2')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A4:C4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('A5:C5')->getFont()->setBold(true);
                $sheet->getStyle('A5:C5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle("A5:C{$totalRow}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A6:C{$lastDateRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A6:A{$lastDateRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle("B6:B{$totalRow}")->getNumberFormat()
                    ->setFormatCode('"$"#,##0.00;[Red]-"$"#,##0.00;"$"-');
                $sheet->getStyle("A{$totalRow}:C{$totalRow}")->getFont()
                    ->setBold(true)
                    ->setItalic(true);

                $sheet->getStyle("A{$notesRow}:C{$notesRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$signatureRow}:C{$signatureRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$signatureNameRow}:C{$signatureNameRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getColumnDimension('A')->setWidth(17);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(5)->setRowHeight(30);

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
                    ->setFitToWidth(1)
                    ->setFitToHeight(1)
                    ->setPrintArea("A1:J{$signatureNameRow}");

                $sheet->getPageMargins()
                    ->setTop(0.5)
                    ->setBottom(0.5)
                    ->setLeft(0.5)
                    ->setRight(0.5);
            },
        ];
    }
}
