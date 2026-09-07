<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class DailyCashReportExport implements FromView, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected string $clubName,
        protected string $cashierName,
        protected string $date,
        protected Collection $payments,
    ) {
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        $methods = $this->payments
            ->map(fn ($payment) => $payment->paymentMethod?->name ?? 'Sin método')
            ->unique()
            ->values();

        if ($methods->isEmpty()) {
            $methods->push('Sin método');
        }

        $tickets = $this->buildTickets($this->payments->where('status', 'registered'), $methods);
        $cancelledTickets = $this->buildTickets($this->payments->where('status', 'cancelled'), $methods);

        return view('exports.daily-cash-report', [
            'clubName' => $this->clubName,
            'cashierName' => $this->cashierName,
            'date' => $this->date,
            'methods' => $methods,
            'tickets' => $tickets,
            'cancelledTickets' => $cancelledTickets,
        ]);
    }

    protected function buildTickets(Collection $payments, Collection $methods): Collection
    {
        return $payments
            ->groupBy(fn ($payment) => $payment->payment_group_id ?: 'payment-' . $payment->id)
            ->map(function (Collection $group) use ($methods) {
                $payment = $group->first();

                return [
                    'folio' => $payment->folio ?? $payment->reference ?? $payment->id,
                    'membership_number' => $payment->membershipAccount?->membership_number ?? '—',
                    'holder_name' => $payment->membershipAccount?->primaryHolder?->member?->full_name ?? '—',
                    'total' => $group->sum('amount'),
                    'methods' => $methods->mapWithKeys(fn ($method) => [
                        $method => $group
                            ->filter(fn ($item) => ($item->paymentMethod?->name ?? 'Sin método') === $method)
                            ->sum('amount'),
                    ]),
                ];
            })
            ->values();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
                    ->setFitToWidth(1);
            },
        ];
    }
}
