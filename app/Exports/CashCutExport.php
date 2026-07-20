<?php

namespace App\Exports;

use App\Models\Billing\CashCut;
use App\Models\Billing\Payment;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Maatwebsite\Excel\Events\AfterSheet;

class CashCutExport implements FromView, ShouldAutoSize, WithEvents
{
    public function __construct(protected CashCut $cashCut)
    {
    }

    public function view(): View
    {
        $tz = config('app.timezone');

        $start = $this->cashCut->date
            ->copy()
            ->startOfDay()
            ->setTimezone($tz)
            ->utc();

        $end = $this->cashCut->date
            ->copy()
            ->endOfDay()
            ->setTimezone($tz)
            ->utc();

        $payments = Payment::with([
                'paymentMethod',
                'membershipAccount'
            ])
            ->where('club_id', $this->cashCut->club_id)
            ->where('received_by', $this->cashCut->user_id)
            ->whereBetween('paid_at', [$start, $end])
            ->whereJsonContains('metadata->settlement_channel', 'cashier')
            ->get();

        $paymentSummary = $payments
            ->groupBy(fn($payment) => $payment->paymentMethod?->name ?? 'Sin método')
            ->map(function ($group) {
                return [
                    'quantity' => $group->count(),
                    'total' => $group->sum('amount'),
                ];
            });

        return view('exports.cash-cut', [
            'cashCut' => $this->cashCut,
            'payments' => $payments,
            'paymentSummary' => $paymentSummary,
            'denominations' => $this->cashCut->denominations,
        ]);
    }

    public function registerEvents(): array
    {
        return [

            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

                $sheet->getPageSetup()
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER);

                $sheet->getPageSetup()
                    ->setFitToWidth(1);

                $sheet->getPageMargins()
                    ->setTop(0.3)
                    ->setBottom(0.3)
                    ->setLeft(0.3)
                    ->setRight(0.3);
            }

        ];
    }
}