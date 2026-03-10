<?php
// routes/Administrator.php

use App\Http\Controllers\Web\Administrator\ClubController;
use App\Http\Controllers\Web\Administrator\PermissionController;
use App\Http\Controllers\Web\Administrator\RoleController;
use App\Http\Controllers\Web\Administrator\UserController;
use Illuminate\Support\Facades\Route;


Route::resource('/roles', RoleController::class)->names('roles');
Route::post('/roles/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate'); 
/* Route::resource('/Modules', ModulesController::class)->names('Modules');*/
Route::resource('/permissions', PermissionController::class)->names('permissions');
Route::resource('/users', UserController::class)->names('users');
Route::resource('/clubs', ClubController::class)->names('clubs');
// php artisan make:model AdminClub/BlockedPeriod -m  && php artisan make:controller Web/AdminClub/BlockedPeriodController --resource