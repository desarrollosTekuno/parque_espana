<?php
// routes/Administrator.php

use App\Http\Controllers\Web\Administrator\ClubController;
use App\Http\Controllers\Web\Administrator\ConektaCredentialController;
use App\Http\Controllers\Web\Administrator\DineroMigrationController;
use App\Http\Controllers\Web\Administrator\MemberAccessController;
use App\Http\Controllers\Web\Administrator\PermissionController;
use App\Http\Controllers\Web\Administrator\RoleController;
use App\Http\Controllers\Web\Administrator\SociosMigrationController;
use App\Http\Controllers\Web\Administrator\UserController;
use Illuminate\Support\Facades\Route;


Route::resource('/roles', RoleController::class)->names('roles');
Route::post('/roles/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');

Route::resource('/permissions', PermissionController::class)->names('permissions');
Route::resource('/users', UserController::class)->names('users');
Route::resource('/clubs', ClubController::class)->names('clubs');

// Carga temporal de la primera fase de migración
Route::get('/socios-migration', [SociosMigrationController::class, 'index'])->name('socios-migration.index');
Route::post('/socios-migration/preview', [SociosMigrationController::class, 'preview'])->name('socios-migration.preview');
Route::post('/socios-migration/import', [SociosMigrationController::class, 'import'])->name('socios-migration.import');

// Carga separada del histórico de cargos y pagos
Route::get('/dinero-migration', [DineroMigrationController::class, 'index'])->name('dinero-migration.index');
Route::post('/dinero-migration/preview', [DineroMigrationController::class, 'preview'])->name('dinero-migration.preview');
Route::post('/dinero-migration/import', [DineroMigrationController::class, 'import'])->name('dinero-migration.import');

Route::post('/change-club', [ClubController::class, 'changeClub'])->name('change.club');

// Credenciales de Conekta por parque (cada parque opera su propia cuenta comercial)
Route::get('/conekta-credentials', [ConektaCredentialController::class, 'index'])->name('conekta-credentials.index');
Route::put('/conekta-credentials', [ConektaCredentialController::class, 'update'])->name('conekta-credentials.update');

// Accesos app móvil
Route::put('/member-access/{member}/reset-password', [MemberAccessController::class, 'resetPassword'])->name('member-access.reset-password');
Route::resource('/member-access', MemberAccessController::class)->only(['index', 'store', 'destroy'])->names('member-access')->parameters(['member-access' => 'member']);
