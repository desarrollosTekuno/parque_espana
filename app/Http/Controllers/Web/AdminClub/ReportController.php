<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Exports\ChargeReportExport;
use App\Exports\CashCutExport;
use App\Exports\CashCollectionByUserReportExport;
use App\Exports\CfdReportExport;
use App\Exports\DailyCashReportExport;
use App\Exports\MonthlyAdministrativeIncomeReportExport;
use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\CashCut;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentApplication;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller {

    public function index() {

        $clubId = (int) session('club_id');
        $List = [
            ['id' => 1, 'name' => 'Reporte de Cobranza'],
            ['id' => 2, 'name' => 'Reporte de Ingresos D.P.E'],
            ['id' => 3, 'name' => 'Resumen Administrativo Mensual de Ingresos'],
            ['id' => 4, 'name' => 'Histórico de cortes de caja'],
            ['id' => 5, 'name' => 'Reporte global diario de caja'],
            ['id' => 6, 'name' => 'Reporte de CFD'],
            ['id' => 7, 'name' => 'Reporte de cobranza por usuario'],
        ];

        $cashiers = CashCut::with('cashier:id,name')
            ->where('club_id', $clubId)
            ->get()
            ->pluck('cashier')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($cashier) => [
                'id' => $cashier->id,
                'name' => $cashier->name,
            ]);

        return Inertia::render('Reports/Index', compact('clubId', 'List', 'cashiers'));
    }

    public function exportCollectionReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $tz = config('app.timezone');
        $clubId = (int) session('club_id');
        $club = $clubId ? Club::find($clubId) : null;

        $start = Carbon::parse($validated['start_date'], $tz)->startOfDay()->utc();
        $end = Carbon::parse($validated['end_date'], $tz)->endOfDay()->utc();

        $filename = 'reporte-cobranza_'.now()->format('ymd-Hisu').'.xlsx';

        return Excel::download(
            new ChargeReportExport($start, $end, $clubId ?: null, $club?->name),
            $filename,
        );
    }

    public function exportMonthlyAdministrativeIncomeReport(Request $request) {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $timezone = config('app.timezone');
        $startDate = Carbon::parse($validated['start_date'], $timezone);
        $endDate = Carbon::parse($validated['end_date'], $timezone);
        $clubId = (int) session('club_id');
        $club = Club::find($clubId);

        $payments = Payment::query()
            ->where('club_id', $clubId)
            ->where('status', 'registered')
            ->whereBetween('paid_at', [
                $startDate->copy()->startOfDay()->utc(),
                $endDate->copy()->endOfDay()->utc(),
            ])
            ->get();

        $filename = 'resumen-administrativo_'.now()->format('ymd-Hisu').'.xlsx';

        return Excel::download(
            new MonthlyAdministrativeIncomeReportExport(
                $club?->name ?? 'Parque España',
                $startDate,
                $endDate,
                $payments,
                $request->user()?->name ?? '',
            ),
            $filename,
        );
    }

    public function exportCashCutsHistoryReport(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'user_id' => ['required', 'integer'],
        ]);

        $clubId = (int) session('club_id');
        $cashCut = CashCut::with('cashier:id,name')
            ->whereDate('date', $validated['date'])
            ->where('club_id', $clubId)
            ->where('status', 'closed')
            ->where('user_id', $validated['user_id'])
            ->firstOrFail();

        $filename = 'historico-cortes-caja_'.now()->format('ymd-Hisu').'.xlsx';

        return Excel::download(new CashCutExport($cashCut), $filename);
    }

    public function exportDailyCashReport(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'user_id' => ['required', 'integer'],
        ]);

        $clubId = (int) session('club_id');
        $club = Club::find($clubId);
        $cashier = CashCut::with('cashier:id,name')
            ->where('club_id', $clubId)
            ->where('user_id', $validated['user_id'])
            ->firstOrFail()
            ->cashier;

        $timezone = config('app.timezone');
        $start = Carbon::parse($validated['date'], $timezone)->startOfDay()->utc();
        $end = Carbon::parse($validated['date'], $timezone)->endOfDay()->utc();

        $payments = Payment::with([
                'paymentMethod',
                'membershipAccount.primaryHolder.member',
            ])
            ->where('club_id', $clubId)
            ->where('received_by', $validated['user_id'])
            ->whereBetween('paid_at', [$start, $end])
            ->whereJsonContains('metadata->settlement_channel', 'cashier')
            ->orderBy('paid_at')
            ->get();

        $filename = 'reporte-global-caja_'.now()->format('ymd-Hisu').'.xlsx';

        return Excel::download(
            new DailyCashReportExport($club?->name ?? 'Parque España', $cashier?->name ?? '', $validated['date'], $payments),
            $filename,
        );
    }

    public function exportCfdReport(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $clubId = (int) session('club_id');
        $club = Club::find($clubId);
        $timezone = config('app.timezone');
        $start = Carbon::parse($validated['date'], $timezone)->startOfDay()->utc();
        $end = Carbon::parse($validated['date'], $timezone)->endOfDay()->utc();

        $payments = Payment::with([
            'membershipAccount.fiscalData',
            'membershipAccount.primaryHolder.member',
            'membershipAccount.memberships' => fn ($query) => $query
                ->where('club_id', $clubId)
                ->where('is_primary', true)
                ->whereIn('status', ['active', 'suspended']),
            'membershipAccount.memberships.membershipType',
            'receiver',
            'paymentMethod.clubPaymentMethods' => fn ($query) => $query->where('club_id', $clubId),
            'applications',
        ])
            ->where('club_id', $clubId)
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at')
            ->get();

        $filename = 'reporte-cfd_'.now()->format('ymd-Hisu').'.xlsx';

        return Excel::download(
            new CfdReportExport(
                $club?->name ?? 'Parque España',
                $validated['date'],
                $payments,
                strtoupper((string) $club?->code) === 'PE2',
            ),
            $filename,
        );
    }

    public function exportCashCollectionByUserReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $timezone = config('app.timezone');
        $clubId = (int) session('club_id');
        $startDate = Carbon::parse($validated['start_date'], $timezone);
        $endDate = Carbon::parse($validated['end_date'], $timezone);
        $applications = PaymentApplication::query()
            ->with([
                'payment.paymentMethod.clubPaymentMethods',
                'payment.receiver:id,name,code',
                'payment.membershipAccount.primaryHolder.member',
                'payment.groupPayments.paymentMethod.clubPaymentMethods',
                'charge.concept',
                'charge.membership.membershipType',
                'charge.membershipAccount.primaryHolder.member',
            ])
            ->whereHas('payment', function ($query) use ($clubId, $startDate, $endDate) {
                $query->where('club_id', $clubId)
                    ->where('status', 'registered')
                    ->whereBetween('paid_at', [
                        $startDate->copy()->startOfDay()->utc(),
                        $endDate->copy()->endOfDay()->utc(),
                    ]);
            })
            ->get();

        $filename = 'reporte-cobranza-por-usuario_'
            .$validated['start_date'].'_a_'.$validated['end_date'].'.xlsx';

        return Excel::download(
            new CashCollectionByUserReportExport($startDate, $endDate, $applications, $clubId),
            $filename,
        );
    }
}
