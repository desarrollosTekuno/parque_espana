<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Exports\CashCutExport;
use App\Models\Billing\CashCut;
use App\Models\Billing\CashCutDenomination;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentMethod;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CashCutController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:cash-cuts.index')->only('index');
        $this->middleware('permission:cash-cuts.store')->only('store');
        $this->middleware('permission:cash-cuts.show')->only(['show', 'export']);
        $this->middleware('permission:cash-cuts.close')->only('close');
    }

    public function export(CashCut $cashCut)
    {
        $filename = 'corte-caja-' . $cashCut->date->format('Y-m-d') . '-' . str($cashCut->cashier?->name ?? 'cajero')->slug() . '.xlsx';
        return Excel::download(new CashCutExport($cashCut), $filename);
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) session('club_id');
            $user = $request->user();
            $canViewAll = $user->can('cash-cuts.view-all');

            $query = CashCut::with(['cashier'])
                ->where('club_id', $clubId)
                ->orderByDesc('date')
                ->orderByDesc('opened_at');

            if (!$canViewAll) {
                $query->where('user_id', $user->id);
            }

            $cuts = $query->paginate(20)->through(fn (CashCut $cut) => $this->buildCutListPayload($cut));

            return Inertia::render('CashCuts/Index', [
                'cuts' => $cuts,
                'canViewAll' => $canViewAll,
            ]);
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('CashCuts/Index', [
                'cuts' => ['data' => [], 'total' => 0],
                'canViewAll' => false,
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'opening_amount' => ['required', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $clubId = (int) session('club_id');
            $userId = $request->user()->id;
            $today = now()->toDateString();

            $existing = CashCut::where('club_id', $clubId)
                ->where('user_id', $userId)
                ->where('date', $today)
                ->first();

            if ($existing) {
                return redirect()->back()->withErrors([
                    'messageError' => 'Ya tienes un corte de caja abierto para hoy.',
                ]);
            }

            CashCut::create([
                'club_id' => $clubId,
                'user_id' => $userId,
                'date' => $today,
                'opening_amount' => $validated['opening_amount'],
                'status' => 'open',
                'opened_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()->route('cash-cuts.index')
                ->with('success', 'Corte de caja abierto correctamente.');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function show(CashCut $cashCut)
    {
        try {
            $cashCut->load(['cashier', 'denominations', 'globalCashCut']);

            $payments = $this->getPaymentsForCut($cashCut);
            $totalsPerMethod = $this->computeTotalsPerMethod($payments);
            $cashTotal = $totalsPerMethod->firstWhere('code', 'CASH')['total'] ?? 0;
            $cashExpected = round($cashCut->opening_amount + $cashTotal, 2);

            $denominations = $cashCut->denominations->isNotEmpty()
                ? $cashCut->denominations->map(fn ($d) => [
                    'denomination' => $d->denomination,
                    'quantity' => $d->quantity,
                    'subtotal' => $d->subtotal,
                ])->values()
                : $this->defaultDenominations();

            return Inertia::render('CashCuts/Show', [
                'cashCut' => [
                    'id' => $cashCut->id,
                    'date' => $cashCut->date->toDateString(),
                    'status' => $cashCut->status,
                    'opening_amount' => $cashCut->opening_amount,
                    'cash_expected' => $cashExpected,
                    'cash_counted' => $cashCut->cash_counted,
                    'cash_difference' => $cashCut->cash_difference,
                    'opened_at' => $cashCut->opened_at?->toIso8601String(),
                    'closed_at' => $cashCut->closed_at?->toIso8601String(),
                    'notes' => $cashCut->notes,
                    'cashier_name' => $cashCut->cashier?->name,
                    'global_cash_cut_id' => $cashCut->global_cash_cut_id,
                ],
                'payments' => $payments,
                'totalsPerMethod' => $totalsPerMethod,
                'denominations' => $denominations,
            ]);
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('cash-cuts.index')
                ->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function close(Request $request, CashCut $cashCut)
    {
        try {
            if (!$cashCut->isOpen()) {
                return redirect()->back()->withErrors(['messageError' => 'Este corte ya está cerrado.']);
            }

            if ($cashCut->user_id !== $request->user()->id && !$request->user()->can('cash-cuts.view-all')) {
                abort(403);
            }

            $validated = $request->validate([
                'notes' => ['nullable', 'string', 'max:1000'],
                'denominations' => ['required', 'array', 'min:1'],
                'denominations.*.denomination' => ['required', 'numeric', 'gt:0'],
                'denominations.*.quantity' => ['required', 'integer', 'min:0'],
            ]);

            $payments = $this->getPaymentsForCut($cashCut);
            $totalsPerMethod = $this->computeTotalsPerMethod($payments);
            $cashTotal = $totalsPerMethod->firstWhere('code', 'CASH')['total'] ?? 0;
            $cashExpected = round($cashCut->opening_amount + $cashTotal, 2);

            $cashCounted = collect($validated['denominations'])->sum(fn ($d) => $d['denomination'] * $d['quantity']);
            $cashCounted = round($cashCounted, 2);
            $cashDifference = round($cashCounted - $cashExpected, 2);

            DB::transaction(function () use ($cashCut, $validated, $cashExpected, $cashCounted, $cashDifference) {
                CashCutDenomination::where('cash_cut_id', $cashCut->id)->delete();

                foreach ($validated['denominations'] as $den) {
                    CashCutDenomination::create([
                        'cash_cut_id' => $cashCut->id,
                        'denomination' => $den['denomination'],
                        'quantity' => $den['quantity'],
                        'subtotal' => round($den['denomination'] * $den['quantity'], 2),
                    ]);
                }

                $cashCut->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                    'cash_expected' => $cashExpected,
                    'cash_counted' => $cashCounted,
                    'cash_difference' => $cashDifference,
                    'notes' => $validated['notes'] ?? $cashCut->notes,
                ]);
            });

            return redirect()->route('cash-cuts.show', $cashCut)
                ->with('success', 'Corte de caja cerrado correctamente.');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    protected function getPaymentsForCut(CashCut $cashCut): \Illuminate\Support\Collection
    {
        $tz = config('app.timezone');
        $start = $cashCut->date->copy()->startOfDay()->setTimezone($tz)->utc();
        $end   = $cashCut->date->copy()->endOfDay()->setTimezone($tz)->utc();

        return Payment::with(['paymentMethod', 'membershipAccount'])
            ->where('received_by', $cashCut->user_id)
            ->whereBetween('paid_at', [$start, $end])
            ->whereJsonContains('metadata->settlement_channel', 'cashier')
            ->where(function ($query) use ($cashCut) {
                // El corte de caja se rige por el parque que estaba activo en la
                // sesión del cajero al momento del cobro (metadata.session_club_id),
                // no por el parque al que pertenece el cargo cobrado. Así, un cobro
                // de un cargo del otro parque cae en el corte del cajero que lo cobró.
                $query->whereJsonContains('metadata->session_club_id', $cashCut->club_id)
                    ->orWhere(function ($fallback) use ($cashCut) {
                        // Compatibilidad con pagos previos a este campo de metadata.
                        $fallback->whereNull('metadata->session_club_id')
                            ->where('club_id', $cashCut->club_id);
                    });
            })
            ->orderBy('paid_at')
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'amount' => (float) $p->amount,
                'payment_method_name' => $p->paymentMethod?->name,
                'payment_method_code' => $p->paymentMethod?->code,
                'membership_number' => $p->membershipAccount?->membership_number,
                'reference' => $p->reference,
            ]);
    }

    protected function computeTotalsPerMethod(\Illuminate\Support\Collection $payments): \Illuminate\Support\Collection
    {
        return $payments
            ->groupBy('payment_method_code')
            ->map(fn ($group, $code) => [
                'code' => $code,
                'name' => $group->first()['payment_method_name'],
                'count' => $group->count(),
                'total' => round($group->sum('amount'), 2),
            ])
            ->values();
    }

    protected function buildCutListPayload(CashCut $cut): array
    {
        return [
            'id' => $cut->id,
            'date' => $cut->date->toDateString(),
            'status' => $cut->status,
            'opening_amount' => $cut->opening_amount,
            'cash_counted' => $cut->cash_counted,
            'cash_expected' => $cut->cash_expected,
            'cash_difference' => $cut->cash_difference,
            'opened_at' => $cut->opened_at?->toIso8601String(),
            'closed_at' => $cut->closed_at?->toIso8601String(),
            'cashier_name' => $cut->cashier?->name,
            'global_cash_cut_id' => $cut->global_cash_cut_id,
        ];
    }

    protected function defaultDenominations(): \Illuminate\Support\Collection
    {
        return collect([1000, 500, 200, 100, 50, 20, 10, 5, 2, 1, 0.50])
            ->map(fn ($d) => ['denomination' => $d, 'quantity' => 0, 'subtotal' => 0]);
    }
}
