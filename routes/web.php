<?php

use App\Http\Controllers\Web\DashboardController;
use App\Services\Auth\PermissionLandingRouteResolver;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

// Las rutas públicas de encuesta por token (/encuesta/{slug}) fueron eliminadas.
// La tabla survey_tokens fue removida en la migración 2026_04_23_000002.
// Las encuestas ahora se responden exclusivamente desde la app móvil (autenticado via Sanctum).
