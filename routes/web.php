<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Survey\SurveyPublicController;
use App\Services\Auth\PermissionLandingRouteResolver;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Billing\CashCut;

Route::middleware(['auth', 'verified'])->get('/', function (PermissionLandingRouteResolver $permissionLandingRouteResolver) {
    $routeName = $permissionLandingRouteResolver->resolve(auth()->user());

    return $routeName
        ? redirect()->route($routeName)
        : redirect()->route('unauthorized');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('unauthorized', fn () => Inertia::render('Errors/Unauthorized'))->name('unauthorized');
});

Route::middleware(['auth', 'verified'])
    ->prefix('superadmin')
    ->group(__DIR__ . '/administrator.php');

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->group(__DIR__ . '/adminclubs.php');

// Encuestas públicas (sin autenticación, acceso por token de usuario)
Route::get('/encuesta/{slug}', [SurveyPublicController::class, 'show'])->name('survey.show');
Route::post('/encuesta/{slug}', [SurveyPublicController::class, 'submit'])->name('survey.submit');


Route::get('/prueba-excel', function () {

    $cashCut = CashCut::with([
        'club',
        'cashier',
        'manager',
        'denominations',
    ])->latest()->first();

    if (!$cashCut) {
        abort(404, 'No existe ningún corte de caja.');
    }

    $tz = config('app.timezone');

    $start = $cashCut->date
        ->copy()
        ->startOfDay()
        ->setTimezone($tz)
        ->utc();

    $end = $cashCut->date
        ->copy()
        ->endOfDay()
        ->setTimezone($tz)
        ->utc();

    $payments = \App\Models\Billing\Payment::with([
            'paymentMethod',
            'membershipAccount'
        ])
        ->where('club_id', $cashCut->club_id)
        ->where('received_by', $cashCut->user_id)
        ->whereBetween('paid_at', [$start, $end])
        ->whereJsonContains('metadata->settlement_channel', 'cashier')
        ->get();

    $paymentSummary = $payments
        ->groupBy(fn ($payment) => $payment->paymentMethod?->name ?? 'Sin método')
        ->map(function ($group) {
            return [
                'quantity' => $group->count(),
                'total' => $group->sum('amount'),
            ];
        });

    return view('exports.cash-cut', [
        'cashCut' => $cashCut,
        'payments' => $payments,
        'paymentSummary' => $paymentSummary,
        'denominations' => $cashCut->denominations,
    ]);

});
