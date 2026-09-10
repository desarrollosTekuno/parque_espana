<?php

namespace App\Exports;

use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class CashCollectionByUserReportExport implements FromArray, ShouldAutoSize, WithEvents {
    public function __construct(
        protected Carbon $startDate,
        protected Carbon $endDate,
        protected Collection $applications,
        protected int $clubId,
    ) {
    }

    public function array(): array {
        $rows = [[
            'Usuario', null, null, null,
            'Cantidad', 'Importe', 'Bonifica.', 'Descuento', 'Efectivo', 'Docto.',
        ]];
        $displayRows = $this->displayRows();

        foreach ($displayRows->groupBy('cashier')->sortKeys() as $cashier => $cashierRows) {
            $rows[] = ['Cobranza registrada por: '.$cashier];

            foreach ($cashierRows->groupBy('concept_id')->sortBy(fn (Collection $conceptRows) => $conceptRows->first()['concept_key']) as $conceptRows) {
                $firstRow = $conceptRows->first();
                $rows[] = ['Concepto de pago: '.$firstRow['concept_key'].' '.$firstRow['concept_name']];

                foreach ($conceptRows->sortBy('paid_at') as $row) {
                    $rows[] = [
                        $row['ticket'],
                        $row['account_number'],
                        $row['membership_number'],
                        $row['holder_name'],
                        $row['quantity'],
                        $row['amount'],
                        $row['bonus'],
                        $row['discount'],
                        $row['cash'],
                        $row['document'],
                    ];
                }
            }

            $cashierTotal = $cashierRows->sum('amount');
            $rows[] = [''];
            $rows[] = [null, null, null, null, null, null, null, null, 'Total de cobranza de '.$cashier, $cashierTotal];
            $rows[] = [null, null, null, null, null, null, null, null, 'Total por Tipo de Pago: '.$this->paymentMethodTotals($cashierRows)];
        }

        $rows[] = [''];
        $rows[] = [null, null, null, null, null, null, null, null, 'Gran Total:', $displayRows->sum('amount')];

        foreach ($this->paymentMethodFinalTotals($displayRows) as $methodTotal) {
            $rows[] = [
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'Total '.$methodTotal['key'].' '.$methodTotal['name'],
                $methodTotal['total'],
            ];
        }

        return $rows;
    }

    public function registerEvents(): array {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->mergeCells('A1:D1');
                $sheet->getStyle('A1:J1')->getFont()->setBold(true);
                $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1:J1')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("F2:I{$lastRow}")->getNumberFormat()->setFormatCode('$#,##0.00');
                $sheet->getStyle("F2:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                for ($row = 2; $row <= $lastRow; $row++) {
                    $label = (string) $sheet->getCell("I{$row}")->getValue();

                    if (str_starts_with($label, 'Gran Total:')) {
                        $sheet->getStyle("I{$row}:J{$row}")->getFont()->setBold(true);
                        $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('$#,##0.00');
                        $sheet->getStyle("A{$row}:J{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                        $sheet->getStyle("A{$row}:J{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
                    } elseif (str_starts_with($label, 'Total de cobranza de ')) {
                        $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('$#,##0.00');
                        $sheet->getStyle("A{$row}:B{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                    } elseif (str_starts_with($label, 'Total por Tipo de Pago:')) {
                        $sheet->mergeCells("I{$row}:J{$row}");
                        $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    } elseif (str_starts_with($label, 'Total ')) {
                        $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('$#,##0.00');
                    }
                }

                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(30);
                foreach (['E', 'F', 'G', 'H', 'I', 'J'] as $column) {
                    $sheet->getColumnDimension($column)->setWidth(14);
                }

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setPrintArea("A1:J{$lastRow}");
            },
        ];
    }

    protected function displayRows(): Collection {
        return $this->applications
            ->groupBy(function (PaymentApplication $application) {
                $payment = $application->payment;
                $group = $payment?->payment_group_id ?: 'payment-'.$payment?->id;

                return $group.'-charge-'.$application->charge_id;
            })
            ->map(function (Collection $applications) {
                $firstApplication = $applications->first();
                $payment = $firstApplication->payment;
                $charge = $firstApplication->charge;
                $account = $payment?->membershipAccount ?? $charge?->membershipAccount;
                $concept = $charge?->concept;
                $payments = $applications->pluck('payment');
                $discount = round((float) $applications->sum('discount'), 2);
                $amount = round((float) $applications->sum('applied_amount') + $discount, 2);

                return [
                    'cashier' => $this->cashierInitials($payment?->receiver),
                    'concept_id' => $concept?->id ?? 0,
                    'concept_key' => $concept?->internal_key ?: $concept?->code ?: 'SIN_CLAVE',
                    'concept_name' => $concept?->name ?? 'Sin concepto',
                    'ticket' => $this->shortFolio($payment?->folio) ?? '',
                    'account_number' => $account?->internal_account_number ?? $account?->membership_number ?? '',
                    'membership_number' => $account?->membership_number ?? '',
                    'holder_name' => $account?->primaryHolder?->member?->full_name ?? '',
                    'quantity' => $this->quantity($charge),
                    'amount' => $amount,
                    'bonus' => 0.0,
                    'discount' => $discount,
                    'cash' => round((float) $applications
                        ->filter(fn (PaymentApplication $item) => strtoupper((string) $item->payment?->paymentMethod?->code) === 'CASH')
                        ->sum('applied_amount'), 2),
                    'document' => $this->documentKey($payments),
                    'paid_at' => $payment?->paid_at?->timestamp ?? 0,
                    'applications' => $applications,
                ];
            })
            ->values();
    }

    protected function paymentMethodTotals(Collection $cashierRows): string {
        return $cashierRows
            ->flatMap(fn (array $row) => $row['applications'])
            ->groupBy(fn (PaymentApplication $application) => $this->singlePaymentMethodKey($application->payment))
            ->map(function (Collection $applications, string $method) {
                $total = (float) $applications->sum('applied_amount') + (float) $applications->sum('discount');

                return $method.' $'.number_format($total, 2);
            })
            ->implode('   ');
    }

    protected function paymentMethodFinalTotals(Collection $rows): Collection {
        return $rows
            ->flatMap(fn (array $row) => $row['applications'])
            ->groupBy(fn (PaymentApplication $application) => $this->singlePaymentMethodKey($application->payment))
            ->map(function (Collection $applications, string $method) {
                $paymentMethod = $applications->first()?->payment?->paymentMethod;

                return [
                    'key' => $method,
                    'name' => $paymentMethod?->name ?? '',
                    'total' => round((float) $applications->sum('applied_amount') + (float) $applications->sum('discount'), 2),
                ];
            })
            ->values();
    }

    protected function quantity($charge): string {
        if ($charge?->period_year && $charge?->period_month) {
            $month = Carbon::create((int) $charge->period_year, (int) $charge->period_month, 1)
                ->locale('es')
                ->translatedFormat('M');

            return '1.0 '.ucfirst($month);
        }

        return number_format((float) ($charge?->metadata['quantity'] ?? 1), 1);
    }

    protected function cashierInitials($cashier): string {
        if ($cashier?->code) {
            return strtoupper($cashier->code);
        }

        $initials = collect(explode(' ', trim((string) $cashier?->name)))
            ->filter()
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->implode('');

        return strtoupper($initials ?: 'SIN CAJERO');
    }

    protected function documentKey(Collection $payments): string {
        if ($payments->pluck('payment_method_id')->filter()->unique()->count() > 1) {
            return 'X';
        }

        return $this->singlePaymentMethodKey($payments->first());
    }

    protected function singlePaymentMethodKey(?Payment $payment): string {
        $method = $payment?->paymentMethod;
        $clubMethod = $method?->clubPaymentMethods->firstWhere('club_id', $this->clubId);

        return $clubMethod?->internal_key ?: $method?->code ?: '';
    }

    protected function shortFolio(?string $folio): ?string {
        if (! $folio) {
            return null;
        }

        $parts = explode('-', $folio);
        $date = $parts[count($parts) - 2] ?? null;
        $consecutive = $parts[count($parts) - 1] ?? null;

        if ($date && $consecutive && preg_match('/^\d{6}$/', $date) && preg_match('/^\d+$/', $consecutive)) {
            return substr($date, -2).str_pad($consecutive, 3, '0', STR_PAD_LEFT);
        }

        return $folio;
    }
}
