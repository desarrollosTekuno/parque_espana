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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class InterclubIncomeReportExport implements FromArray, WithEvents, WithTitle {
    public function __construct(
        protected string $sourceClubName,
        protected string $targetClubName,
        protected int $targetClubId,
        protected Carbon $startDate,
        protected Carbon $endDate,
        protected Collection $payments,
        protected string $deliveredBy,
        protected ?string $logoContent = null,
    ) {}

    public function title(): string {
        return 'CobradoParaPE';
    }

    public function array(): array {
        $title = "Resumen Administrativo de ingresos cobrados en Deportivo {$this->sourceClubName} para {$this->targetClubName}";
        $period = 'Reporte del '.$this->startDate->format('d/m/y').' al '.$this->endDate->format('d/m/y');
        $rows = [
            array_fill(0, 8, null),
            [null, $title, null, null, null, null, null, null],
            array_fill(0, 8, null),
            [null, null, null, null, null, $period, null, null],
            array_fill(0, 8, null),
            [
                'Día',
                'TOTAL',
                'Fecha Depósito',
                'Efectivo',
                "Cheque\n{$this->targetClubName}",
                "Tarj Crédito\n{$this->targetClubName}",
                "Tarj Débito\n{$this->targetClubName}",
                "Transferencia\n{$this->targetClubName}",
            ],
        ];

        $date = $this->startDate->copy()->startOfDay();
        $endDate = $this->endDate->copy()->startOfDay();
        $paymentTotals = array_fill(0, 5, 0.0);
        $paymentsByDate = $this->payments->groupBy(
            fn (Payment $payment) => $payment->paid_at->copy()->setTimezone(config('app.timezone'))->toDateString()
        );
        $groupsWithTargetPayments = $this->payments
            ->filter(fn (Payment $payment) => (int) ($payment->metadata['represents_club_id'] ?? 0) === $this->targetClubId)
            ->mapWithKeys(fn (Payment $payment) => [$this->paymentGroupKey($payment) => true]);

        while ($date->lte($endDate)) {
            $amounts = array_fill(0, 5, 0.0);
            $dayPayments = $paymentsByDate->get($date->toDateString(), collect());

            foreach ($dayPayments as $payment) {
                $column = $this->paymentColumn($payment);

                if ($column !== null) {
                    $amount = $this->interclubAmount($payment, $groupsWithTargetPayments);
                    $amounts[$column] += $amount;
                    $paymentTotals[$column] += $amount;
                }
            }

            $rows[] = [Date::PHPToExcel($date), round(array_sum($amounts), 2), null, ...$amounts];
            $date->addDay();
        }

        $rows[] = ['TOTAL', round(array_sum($paymentTotals), 2), null, ...$paymentTotals];
        $rows[] = array_fill(0, 8, null);
        $rows[] = array_fill(0, 8, null);
        $rows[] = [null, 'Notas y Observaciones', null, null, null, null, null, null];
        $rows[] = array_fill(0, 8, null);
        $rows[] = array_fill(0, 8, null);
        $rows[] = array_fill(0, 8, null);
        $rows[] = [null, null, 'ENTREGA', null, null, null, null, null];
        $rows[] = array_fill(0, 8, null);
        $rows[] = [null, null, $this->deliveredBy, null, null, null, null, null];

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

                $sheet->mergeCells('B2:H2');
                $sheet->mergeCells('F4:H4');

                $sheet->getStyle('B2')->getFont()
                    ->setBold(true)
                    ->setSize(16);
                $sheet->getStyle('B2')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('F4:H4')->getFont()
                    ->setBold(true)
                    ->setSize(12);
                $sheet->getStyle('F4:H4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('F4:H4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFFFFF00');

                $sheet->getRowDimension(1)->setRowHeight(18);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(18);
                $sheet->getRowDimension(4)->setRowHeight(22);
                $sheet->getRowDimension(6)->setRowHeight(42);

                $sheet->getStyle('A6:H6')->getAlignment()
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
                $sheet->getStyle("A6:H{$totalRow}")->applyFromArray($tableBorder);

                $sheet->getStyle("A7:A{$lastDateRow}")
                    ->getNumberFormat()
                    ->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle("A7:C{$lastDateRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("B7:B{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"$"#,##0.00;[Red]-"$"#,##0.00;"$"-');
                $sheet->getStyle("D7:H{$totalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"$"#,##0.00;[Red]-"$"#,##0.00;"$"-');

                $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFont()
                    ->setBold(true)
                    ->setItalic(true);

                $sheet->mergeCells("B{$notesRow}:C{$notesRow}");
                $sheet->mergeCells("C{$signatureLabelRow}:E{$signatureLabelRow}");
                $sheet->mergeCells("C{$signatureNameRow}:E{$signatureNameRow}");

                $sheet->getStyle("C{$signatureLabelRow}:E{$signatureNameRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("C{$signatureLabelRow}:E{$signatureLabelRow}")->getFont()
                    ->setBold(true);

                $sheet->getColumnDimension('A')->setWidth(13);
                $sheet->getColumnDimension('B')->setWidth(16);
                $sheet->getColumnDimension('C')->setWidth(14);
                foreach (range('D', 'H') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(22);
                }

                $sheet->getPageSetup()->setPrintArea("A1:H{$signatureNameRow}");
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1);

                $this->addLogo($sheet);
            },
        ];
    }

    private function interclubAmount(Payment $payment, Collection $groupsWithTargetPayments): float {
        $representedClubId = (int) ($payment->metadata['represents_club_id'] ?? 0);

        if ($groupsWithTargetPayments->has($this->paymentGroupKey($payment))) {
            return $representedClubId === $this->targetClubId ? round((float) $payment->amount, 2) : 0.0;
        }

        $splitAmount = $payment->applications->sum(function ($application) {
            $charge = $application->charge;
            $membership = $charge?->membership;
            $isInterclubMonthlyFee = $charge?->concept?->code === 'MONTHLY_FEE'
                && (
                    (bool) $membership?->interclub_package_rule_id
                    || (bool) ($membership?->pricingRule?->requires_multiple_clubs ?? false)
                );

            if (! $isInterclubMonthlyFee) {
                return 0;
            }

            return (float) $application->applied_amount;
        });

        return round($splitAmount / 2, 2);
    }

    private function paymentGroupKey(Payment $payment): string {
        return $payment->payment_group_id ?: 'payment-'.$payment->id;
    }

    private function paymentColumn(Payment $payment): ?int {
        return match (strtoupper((string) $payment->paymentMethod?->code)) {
            'CASH' => 0,
            'CHECK' => 1,
            'CREDIT_CARD' => 2,
            'DEBIT_CARD' => 3,
            'BANK_TRANSFER', 'SPEI' => 4,
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
