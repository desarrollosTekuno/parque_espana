<?php

use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect(route('login'));
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'verified'])
    ->prefix('superadmin')
    ->group(__DIR__ . '/administrator.php');

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->group(__DIR__ . '/adminclubs.php');