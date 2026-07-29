<?php

namespace App\Exports;

use App\Models\Billing\PaymentApplication;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ChargeReportExport implements FromView, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected Carbon $start,
        protected Carbon $end,
        protected ?int $clubId = null,
        protected ?string $clubName = null,
    ) {
    }

    public function view(): View
    {
        $applications = PaymentApplication::query()
            ->with([
                'payment.paymentMethod',
                'charge.concept',
                'charge.membershipAccount',
                'charge.membership.membershipType',
            ])
            ->whereHas('payment', function ($query) {
                $query->where('status', 'registered')
                    ->whereBetween('paid_at', [$this->start, $this->end]);

                if ($this->clubId) {
                    $query->where('club_id', $this->clubId);
                }
            })
            ->get();

        // Total aplicado por pago, para repartir el descuento de anualidad
        // proporcionalmente entre los cargos que ese pago liquidó.
        $totalsByPayment = $applications
            ->groupBy('payment_id')
            ->map(fn ($group) => (float) $group->sum('applied_amount'));

        $rows = $applications->map(function (PaymentApplication $application) use ($totalsByPayment) {
            $payment = $application->payment;
            $charge = $application->charge;
            $concept = $charge?->concept;

            $isCash = $payment?->paymentMethod?->code === 'CASH';
            $applied = (float) $application->applied_amount;

            $metadata = is_array($payment?->metadata) ? $payment->metadata : [];
            $discountTotal = (float) ($metadata['discount_amount'] ?? 0);
            $paymentTotalApplied = (float) ($totalsByPayment[$application->payment_id] ?? 0);

            $discountShare = ($discountTotal > 0 && $paymentTotalApplied > 0)
                ? round($discountTotal * ($applied / $paymentTotalApplied), 2)
                : 0.0;

            return [
                'concept_code' => $concept?->code ?? 'SIN_CONCEPTO',
                'concept_name' => $concept?->name ?? 'Sin concepto',
                'user_code' => $charge?->membershipAccount?->membership_number ?? '—',
                'membership_type' => $charge?->membership?->membershipType?->name ?? '—',
                'paid_at' => $payment?->paid_at?->copy()->setTimezone(config('app.timezone')),
                'cantidad' => $applied,
                'importe' => (float) ($charge?->amount ?? 0),
                'bonificacion' => 0.0,
                'descuento' => $discountShare,
                'efectivo' => $isCash ? $applied : 0.0,
            ];
        });

        $groups = $rows
            ->sortBy(fn ($row) => optional($row['paid_at'])->timestamp)
            ->groupBy('concept_code')
            ->map(function ($group) {
                return [
                    'concept_code' => $group->first()['concept_code'],
                    'concept_name' => $group->first()['concept_name'],
                    'rows' => $group->values(),
                    'totals' => [
                        'cantidad' => (float) $group->sum('cantidad'),
                        'importe' => (float) $group->sum('importe'),
                        'bonificacion' => (float) $group->sum('bonificacion'),
                        'descuento' => (float) $group->sum('descuento'),
                        'efectivo' => (float) $group->sum('efectivo'),
                    ],
                ];
            })
            ->sortBy('concept_code')
            ->values();

        $grandTotals = [
            'cantidad' => (float) $rows->sum('cantidad'),
            'importe' => (float) $rows->sum('importe'),
            'bonificacion' => (float) $rows->sum('bonificacion'),
            'descuento' => (float) $rows->sum('descuento'),
            'efectivo' => (float) $rows->sum('efectivo'),
        ];

        return view('exports.charges-report', [
            'groups' => $groups,
            'grandTotals' => $grandTotals,
            'clubName' => $this->clubName,
            'start' => $this->start->copy()->setTimezone(config('app.timezone')),
            'end' => $this->end->copy()->setTimezone(config('app.timezone')),
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
            },
        ];
    }
}
