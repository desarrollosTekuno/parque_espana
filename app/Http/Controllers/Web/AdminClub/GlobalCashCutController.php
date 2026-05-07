<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Exports\GlobalCashCutExport;
use App\Models\Billing\CashCut;
use App\Models\Billing\GlobalCashCut;
use App\Models\Billing\Payment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class GlobalCashCutController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:global-cash-cuts.index')->only('index');
        $this->middleware('permission:global-cash-cuts.store')->only('store');
        $this->middleware('permission:global-cash-cuts.show')->only(['show', 'export']);
        $this->middleware('permission:global-cash-cuts.close')->only('close');
    }

    public function export(GlobalCashCut $globalCashCut)
    {
        $filename = 'corte-global-' . $globalCashCut->date->format('Y-m-d') . '.xlsx';
        return Excel::download(new GlobalCashCutExport($globalCashCut), $filename);
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) session('club_id');

            $cuts = GlobalCashCut::with(['createdBy'])
                ->where('club_id', $clubId)
                ->orderByDesc('date')
                ->paginate(20)
                ->through(fn (GlobalCashCut $cut) => [
                    'id' => $cut->id,
                    'date' => $cut->date->toDateString(),
                    'status' => $cut->status,
                    'created_by_name' => $cut->createdBy?->name,
                    'closed_at' => $cut->closed_at?->toIso8601String(),
                    'individual_cuts_count' => CashCut::where('global_cash_cut_id', $cut->id)->count(),
                ]);

            $pendingCuts = CashCut::with(['cashier'])
                ->where('club_id', $clubId)
                ->where('status', 'closed')
                ->whereNull('global_cash_cut_id')
                ->orderByDesc('date')
                ->get()
                ->map(fn (CashCut $c) => [
                    'id' => $c->id,
                    'date' => $c->date->toDateString(),
                    'cashier_name' => $c->cashier?->name,
                    'cash_counted' => $c->cash_counted,
                    'cash_difference' => $c->cash_difference,
                ]);

            return Inertia::render('GlobalCashCuts/Index', [
                'cuts' => $cuts,
                'pendingCuts' => $pendingCuts,
            ]);
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('GlobalCashCuts/Index', [
                'cuts' => ['data' => [], 'total' => 0],
                'pendingCuts' => [],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $clubId = (int) session('club_id');

            $validated = $request->validate([
                'date' => ['required', 'date'],
                'cash_cut_ids' => ['required', 'array', 'min:1'],
                'cash_cut_ids.*' => ['required', 'integer'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $existing = GlobalCashCut::where('club_id', $clubId)
                ->where('date', $validated['date'])
                ->first();

            if ($existing) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Ya existe un corte global para esa fecha.',
                ]);
            }

            $cashCuts = CashCut::whereIn('id', $validated['cash_cut_ids'])
                ->where('club_id', $clubId)
                ->where('status', 'closed')
                ->whereNull('global_cash_cut_id')
                ->get();

            if ($cashCuts->isEmpty()) {
                return redirect()->back()->withErrors([
                    'messageError' => 'No hay cortes individuales cerrados disponibles para consolidar.',
                ]);
            }

            DB::transaction(function () use ($clubId, $validated, $cashCuts, $request) {
                $globalCut = GlobalCashCut::create([
                    'club_id' => $clubId,
                    'date' => $validated['date'],
                    'status' => 'open',
                    'created_by' => $request->user()->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                CashCut::whereIn('id', $cashCuts->pluck('id'))
                    ->update(['global_cash_cut_id' => $globalCut->id]);
            });

            return redirect()->route('global-cash-cuts.index')
                ->with('success', 'Corte global creado correctamente.');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function show(GlobalCashCut $globalCashCut)
    {
        try {
            $globalCashCut->load(['cashCuts.cashier', 'cashCuts.denominations', 'createdBy']);

            $tz = config('app.timezone');

            $cashCuts = $globalCashCut->cashCuts->map(function (CashCut $cut) use ($tz) {
                $start = $cut->date->copy()->startOfDay()->setTimezone($tz)->utc();
                $end   = $cut->date->copy()->endOfDay()->setTimezone($tz)->utc();

                $payments = Payment::with('paymentMethod')
                    ->where('club_id', $cut->club_id)
                    ->where('received_by', $cut->user_id)
                    ->whereBetween('paid_at', [$start, $end])
                    ->whereJsonContains('metadata->settlement_channel', 'cashier')
                    ->get();

                $totalsPerMethod = $payments
                    ->groupBy(fn ($p) => $p->paymentMethod?->code)
                    ->map(fn ($group, $code) => [
                        'code' => $code,
                        'name' => $group->first()->paymentMethod?->name,
                        'total' => round($group->sum('amount'), 2),
                    ])
                    ->values();

                return [
                    'id' => $cut->id,
                    'date' => $cut->date->toDateString(),
                    'cashier_name' => $cut->cashier?->name,
                    'opening_amount' => $cut->opening_amount,
                    'cash_expected' => $cut->cash_expected,
                    'cash_counted' => $cut->cash_counted,
                    'cash_difference' => $cut->cash_difference,
                    'totals_per_method' => $totalsPerMethod,
                    'denominations' => $cut->denominations->map(fn ($d) => [
                        'denomination' => $d->denomination,
                        'quantity' => $d->quantity,
                        'subtotal' => $d->subtotal,
                    ])->values(),
                ];
            });

            // Consolidado global por método de pago
            $globalStart = $globalCashCut->date->copy()->startOfDay()->setTimezone($tz)->utc();
            $globalEnd   = $globalCashCut->date->copy()->endOfDay()->setTimezone($tz)->utc();

            $allPayments = Payment::with('paymentMethod')
                ->where('club_id', $globalCashCut->club_id)
                ->whereBetween('paid_at', [$globalStart, $globalEnd])
                ->whereIn('received_by', $globalCashCut->cashCuts->pluck('user_id'))
                ->whereJsonContains('metadata->settlement_channel', 'cashier')
                ->get();

            $consolidatedTotals = $allPayments
                ->groupBy(fn ($p) => $p->paymentMethod?->code)
                ->map(fn ($group, $code) => [
                    'code' => $code,
                    'name' => $group->first()->paymentMethod?->name,
                    'count' => $group->count(),
                    'total' => round($group->sum('amount'), 2),
                ])
                ->values();

            return Inertia::render('GlobalCashCuts/Show', [
                'globalCashCut' => [
                    'id' => $globalCashCut->id,
                    'date' => $globalCashCut->date->toDateString(),
                    'status' => $globalCashCut->status,
                    'created_by_name' => $globalCashCut->createdBy?->name,
                    'closed_at' => $globalCashCut->closed_at?->toIso8601String(),
                    'notes' => $globalCashCut->notes,
                ],
                'cashCuts' => $cashCuts,
                'consolidatedTotals' => $consolidatedTotals,
                'grandTotal' => round($allPayments->sum('amount'), 2),
            ]);
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('global-cash-cuts.index')
                ->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function close(Request $request, GlobalCashCut $globalCashCut)
    {
        try {
            if (!$globalCashCut->isOpen()) {
                return redirect()->back()->withErrors(['messageError' => 'Este corte global ya está cerrado.']);
            }

            $validated = $request->validate([
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $globalCashCut->update([
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => $validated['notes'] ?? $globalCashCut->notes,
            ]);

            return redirect()->route('global-cash-cuts.show', $globalCashCut)
                ->with('success', 'Corte global cerrado correctamente.');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }
}
