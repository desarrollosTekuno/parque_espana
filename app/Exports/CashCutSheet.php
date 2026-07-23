<?php

namespace App\Exports;

use App\Models\Billing\CashCut;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class CashCutSheet implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    protected CashCut $cashCut;

    public function __construct(
        protected $date,
        protected int $userId,
        protected int $clubId
    ) {
    }

    public function title(): string
    {
        return 'PE' . $this->clubId;
    }

    public function view(): View
    {
        $cashCut = CashCut::with([
                'club',
                'cashier',
                'denominations',
            ])
            ->whereDate('date', $this->date)
            ->where('club_id', $this->clubId)
            ->where('user_id', $this->userId)
            ->first();

        // Si no hubo corte para este club, mostramos la hoja vacía
        if (!$cashCut) {

            return view('exports.cash-cut', [
                'cashCut' => null,
                'club' => null,
                'payments' => collect(),
                'paymentSummary' => collect(),
                'denominations' => collect(),
            ]);
        }

        $payments = $cashCut
            ->payments()
            ->with([
                'paymentMethod',
                'membershipAccount',
            ])
            ->get();

        $paymentSummary = $payments
            ->groupBy(fn ($payment) => $payment->paymentMethod?->name ?? 'Sin método')
            ->map(function ($group, $method) {
                return [
                    'method' => $method,
                    'quantity' => $group->count(),
                    'total' => $group->sum('amount'),
                ];
            })
            ->values();

        return view('exports.cash-cut', [
            'cashCut' => $cashCut,
            'club' => $cashCut->club,
            'payments' => $payments,
            'paymentSummary' => $paymentSummary,
            'denominations' => $cashCut->denominations,
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
                    ->setTop(0.30)
                    ->setBottom(0.30)
                    ->setLeft(0.30)
                    ->setRight(0.30);

                // Columnas
                $sheet->getColumnDimension('A')->setWidth(18);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(4);
                $sheet->getColumnDimension('E')->setWidth(24);
                $sheet->getColumnDimension('F')->setWidth(18);
                $sheet->getColumnDimension('G')->setWidth(18);

                // Centrar horizontalmente
                $sheet->getPageSetup()->setHorizontalCentered(true);
            }

        ];
    }
}