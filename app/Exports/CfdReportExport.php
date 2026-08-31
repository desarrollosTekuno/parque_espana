<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class CfdReportExport implements FromArray, ShouldAutoSize, WithEvents, WithStrictNullComparison, WithTitle
{
    public function __construct(
        protected string $clubName,
        protected string $date,
        protected Collection $payments,
        protected bool $showsTax,
    ) {
    }

    public function title(): string
    {
        return 'REPORTE CFD';
    }

    public function array(): array
    {
        $rows = [
            [Str::upper($this->clubName)],
            ['REPORTE DE CFD DE '.Str::upper($this->clubName)],
            ['FECHA DEL REPORTE: '.Carbon::parse($this->date)->format('d/m/Y')],
            array_fill(0, 13, null),
            [
                'FECHA',
                'CFD',
                'Num.',
                'USUARIO',
                'RFC',
                'NOMBRE TITULAR',
                'TIPO DE MEMBRESÍA',
                'SUBTOTAL',
                'DESCUENTO',
                'IMPUESTO',
                'TOTAL',
                'CANC.',
                'M.P.',
            ],
        ];

        foreach ($this->payments as $payment) {
            $discount = round((float) $payment->applications->sum('discount'), 2);
            $membershipTypes = $payment->applications
                ->map(fn ($application) => $application->charge?->membership?->membershipType?->name)
                ->filter()
                ->unique()
                ->implode(' / ');
            $clubPaymentMethod = $payment->paymentMethod?->clubPaymentMethods->first();

            $rows[] = [
                $payment->paid_at?->copy()->setTimezone(config('app.timezone'))->format('d/m/Y'),
                '',
                '',
                Str::upper($payment->membershipAccount?->membership_number ?? ''),
                Str::upper($payment->membershipAccount?->fiscalData?->rfc ?: 'XAXX010101000'),
                Str::upper($payment->membershipAccount?->primaryHolder?->member?->full_name ?? ''),
                Str::upper($membershipTypes),
                $payment->subtotal !== null ? round((float) $payment->subtotal, 2) : round((float) $payment->amount, 2),
                $discount,
                $this->showsTax ? round((float) ($payment->iva ?? 0), 2) : 'N/A',
                round((float) $payment->amount, 2),
                $payment->status === 'cancelled' ? 'C' : '',
                Str::upper($clubPaymentMethod?->internal_key ?: $payment->paymentMethod?->code ?? ''),
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->payments->count());

                $sheet->mergeCells('A1:M1');
                $sheet->mergeCells('A2:M2');
                $sheet->mergeCells('A3:M3');

                $sheet->getStyle('A1:M3')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A5:M5')->getFont()->setBold(true);
                $sheet->getStyle('A5:M5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A5:M5')->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);

                if ($lastRow > 5) {
                    $sheet->getStyle("H6:K{$lastRow}")->getNumberFormat()
                        ->setFormatCode('"$"#,##0.00');
                    $sheet->getStyle("H6:K{$lastRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A6:E{$lastRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("L6:M{$lastRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getColumnDimension('A')->setWidth(13);
                $sheet->getColumnDimension('B')->setWidth(9);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(18);
                $sheet->getColumnDimension('F')->setWidth(32);
                $sheet->getColumnDimension('G')->setWidth(22);
                foreach (range('H', 'K') as $column) {
                    $sheet->getColumnDimension($column)->setWidth(14);
                }
                $sheet->getColumnDimension('L')->setWidth(10);
                $sheet->getColumnDimension('M')->setWidth(10);

                $sheet->freezePane('A6');
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
                    ->setFitToWidth(1);
                $sheet->getPageSetup()->setPrintArea("A1:M{$lastRow}");
            },
        ];
    }
}
