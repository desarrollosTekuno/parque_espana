<?php

namespace App\Exports;

use App\Models\Billing\CashCut;
use App\Models\Billing\Payment;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class CashCutExport implements WithMultipleSheets
{
    public function __construct(protected CashCut $cashCut) {}

    public function sheets(): array
    {
        $tz    = config('app.timezone');
        $start = $this->cashCut->date->copy()->startOfDay()->setTimezone($tz)->utc();
        $end   = $this->cashCut->date->copy()->endOfDay()->setTimezone($tz)->utc();

        $payments = Payment::with(['paymentMethod', 'membershipAccount'])
            ->where('club_id', $this->cashCut->club_id)
            ->where('received_by', $this->cashCut->user_id)
            ->whereBetween('paid_at', [$start, $end])
            ->whereJsonContains('metadata->settlement_channel', 'cashier')
            ->orderBy('paid_at')
            ->get();

        return [
            new CashCutSummarySheet($this->cashCut, $payments),
            new CashCutPaymentsSheet($payments),
            new CashCutDenominationsSheet($this->cashCut),
        ];
    }
}

class CashCutSummarySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected CashCut $cashCut,
        protected Collection $payments,
    ) {}

    public function title(): string { return 'Resumen'; }

    public function headings(): array { return ['Campo', 'Valor']; }

    public function collection(): Collection
    {
        $cashCut = $this->cashCut;
        $cashTotal = $this->payments->where('paymentMethod.code', 'CASH')->sum('amount');

        $rows = collect([
            ['Cajero/a', $cashCut->cashier?->name],
            ['Fecha', $cashCut->date->format('d/m/Y')],
            ['Apertura', $cashCut->opened_at?->format('d/m/Y H:i')],
            ['Cierre', $cashCut->closed_at?->format('d/m/Y H:i') ?? 'Abierto'],
            ['Fondo inicial', $cashCut->opening_amount],
            ['Efectivo en cobros', $cashTotal],
            ['Efectivo esperado', $cashCut->cash_expected],
            ['Efectivo contado', $cashCut->cash_counted],
            ['Diferencia', $cashCut->cash_difference],
            ['Total cobros', $this->payments->sum('amount')],
            ['Número de transacciones', $this->payments->count()],
        ]);

        $this->payments
            ->groupBy(fn ($p) => $p->paymentMethod?->name ?? 'Sin método')
            ->each(function ($group, $name) use (&$rows) {
                $rows->push(["Total – {$name}", $group->sum('amount')]);
            });

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

class CashCutPaymentsSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected Collection $payments) {}

    public function title(): string { return 'Cobros'; }

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

class CashCutDenominationsSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected CashCut $cashCut) {}

    public function title(): string { return 'Conteo de efectivo'; }

    public function headings(): array
    {
        return ['Denominación', 'Cantidad', 'Subtotal'];
    }

    public function collection(): Collection
    {
        $rows = $this->cashCut->denominations->map(fn ($d) => [
            '$' . number_format($d->denomination, 2),
            $d->quantity,
            $d->subtotal,
        ]);

        $rows->push(['TOTAL', '', $this->cashCut->cash_counted]);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
