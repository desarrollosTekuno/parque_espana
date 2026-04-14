<?php

use App\Http\Controllers\Web\DashboardController;
use App\Services\Auth\PermissionLandingRouteResolver;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (PermissionLandingRouteResolver $permissionLandingRouteResolver) {
    if (!auth()->check()) {
        return redirect(route('login'));
    }

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
