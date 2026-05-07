<?php

namespace App\Exports;

use App\Models\Billing\GlobalCashCut;
use App\Models\Billing\Payment;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class GlobalCashCutExport implements WithMultipleSheets
{
    public function __construct(protected GlobalCashCut $globalCashCut) {}

    public function sheets(): array
    {
        $this->globalCashCut->load(['cashCuts.cashier', 'cashCuts.denominations', 'createdBy']);

        $sheets = [new GlobalCashCutConsolidatedSheet($this->globalCashCut)];

        foreach ($this->globalCashCut->cashCuts as $cashCut) {
            $tz    = config('app.timezone');
            $start = $cashCut->date->copy()->startOfDay()->setTimezone($tz)->utc();
            $end   = $cashCut->date->copy()->endOfDay()->setTimezone($tz)->utc();

            $payments = Payment::with(['paymentMethod', 'membershipAccount'])
                ->where('club_id', $cashCut->club_id)
                ->where('received_by', $cashCut->user_id)
                ->whereBetween('paid_at', [$start, $end])
                ->whereJsonContains('metadata->settlement_channel', 'cashier')
                ->orderBy('paid_at')
                ->get();

            $sheets[] = new GlobalCashCutIndividualSheet($cashCut, $payments);
        }

        return $sheets;
    }
}

class GlobalCashCutConsolidatedSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected GlobalCashCut $globalCashCut) {}

    public function title(): string { return 'Consolidado'; }

    public function headings(): array { return ['Cajero/a', 'Fondo inicial', 'Efectivo esperado', 'Efectivo contado', 'Diferencia', 'Total cobrado']; }

    public function collection(): Collection
    {
        $rows = $this->globalCashCut->cashCuts->map(function ($cut) {
            $payments = Payment::where('club_id', $cut->club_id)
                ->where('received_by', $cut->user_id)
                ->whereDate('paid_at', $cut->date)
                ->whereJsonContains('metadata->settlement_channel', 'cashier')
                ->sum('amount');

            return [
                $cut->cashier?->name,
                $cut->opening_amount,
                $cut->cash_expected,
                $cut->cash_counted,
                $cut->cash_difference,
                $payments,
            ];
        });

        $rows->push([
            'TOTAL',
            $this->globalCashCut->cashCuts->sum('opening_amount'),
            $this->globalCashCut->cashCuts->sum('cash_expected'),
            $this->globalCashCut->cashCuts->sum('cash_counted'),
            $this->globalCashCut->cashCuts->sum('cash_difference'),
            '',
        ]);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

class GlobalCashCutIndividualSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected $cashCut,
        protected Collection $payments,
    ) {}

    public function title(): string
    {
        $name = substr($this->cashCut->cashier?->name ?? 'Cajero', 0, 25);
        return $name;
    }

    public function headings(): array
    {
        return ['Hora', 'No. Cuenta', 'Método de pago', 'Referencia', 'Importe'];
    }

    public function collection(): Collection
    {
        return $this->payments->map(fn ($p) => [
            $p->paid_at?->format('H:i'),
            $p->membershipAccount?->membership_number,
            $p->paymentMethod?->name,
            $p->reference,
            $p->amount,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
