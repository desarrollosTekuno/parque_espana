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
    ) {}

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
            array_fill(0, 12, null),
            [
                'FECHA',
                'CFD',
                'Num.',
                'USUARIO',
                'RFC',
                'NOMBRE TITULAR',
                'TIPO',
                'SUBTOTAL',
                'DESCUENTO',
                'IMPUESTO',
                'TOTAL',
                'CANC.',
                'M.P.',
            ],
        ];

        $paymentGroups = $this->payments->groupBy(
            fn ($payment) => $payment->payment_group_id ?: 'payment-'.$payment->id
        );

        foreach ($paymentGroups as $payments) {
            $payment = $payments->sortBy('id')->first();
            $applications = $payments->flatMap(fn ($item) => $item->applications);
            $discount = round((float) $applications->sum('discount'), 2);
            $cashierCode = trim((string) $payment->receiver?->code);

            if ($cashierCode === '') {
                $words = preg_split('/\s+/', trim(Str::ascii((string) $payment->receiver?->name))) ?: [];

                foreach ($words as $word) {
                    if ($word !== '') {
                        $cashierCode .= strtoupper(substr($word, 0, 1));
                    }
                }
            }

            $ticketNumber = $payment->folio;

            if ($payment->folio) {
                $folioParts = explode('-', $payment->folio);
                $folioDate = $folioParts[count($folioParts) - 2] ?? null;
                $folioConsecutive = $folioParts[count($folioParts) - 1] ?? null;

                if ($folioDate && $folioConsecutive && preg_match('/^\d{6}$/', $folioDate) && preg_match('/^\d+$/', $folioConsecutive)) {
                    $ticketNumber = substr($folioDate, -2).str_pad($folioConsecutive, 3, '0', STR_PAD_LEFT);
                }
            }

            $membershipTypeName = $payment->membershipAccount?->memberships->first()?->membershipType?->name ?? '';
            $membershipType = Str::contains(Str::lower($membershipTypeName), 'familiar') ? 'F' : 'I';
            $paymentMethodIds = $payments->pluck('payment_method_id')->filter()->unique();
            $clubPaymentMethod = $payment->paymentMethod?->clubPaymentMethods->first();
            $paymentMethod = $paymentMethodIds->count() > 1
                ? 'X'
                : Str::upper($clubPaymentMethod?->internal_key ?: $payment->paymentMethod?->code ?? '');
            $subtotal = $payments->sum(
                fn ($item) => $item->subtotal !== null ? (float) $item->subtotal : (float) $item->amount
            );

            $rows[] = [
                $payment->paid_at?->copy()->setTimezone(config('app.timezone'))->format('d/m/Y'),
                Str::upper($cashierCode),
                $ticketNumber,
                Str::upper($payment->membershipAccount?->membership_number ?? ''),
                Str::upper($payment->membershipAccount?->fiscalData?->rfc ?: 'XAXX010101000'),
                Str::upper($payment->membershipAccount?->primaryHolder?->member?->full_name ?? ''),
                $membershipType,
                round($subtotal, 2),
                $discount,
                $this->showsTax ? round((float) $payments->sum('iva'), 2) : 'N/A',
                round((float) $payments->sum('amount'), 2),
                $payments->contains('status', 'cancelled') ? 'C' : '',
                $paymentMethod,
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $paymentGroups = $this->payments->groupBy(
                    fn ($payment) => $payment->payment_group_id ?: 'payment-'.$payment->id
                );
                $lastRow = max(5, 5 + $paymentGroups->count());

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
