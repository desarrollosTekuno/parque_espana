<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Exports\ChargeReportExport;
use App\Exports\MonthlyAdministrativeIncomeReportExport;
use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Billing\Payment;
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
        ];

        return Inertia::render('Reports/Index', compact('clubId', 'List'));
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

        $filename = 'reporte-cobranza-' . $validated['start_date'] . '-a-' . $validated['end_date'] . '.xlsx';

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

        $filename = 'resumen-administrativo-mensual-'.$validated['start_date'].'-a-'.$validated['end_date'].'.xlsx';

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
}
