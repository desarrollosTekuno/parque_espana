<?php
// routes/Administrator.php

use App\Http\Controllers\Web\Administrator\ClubController;
use App\Http\Controllers\Web\Administrator\MemberAccessController;
use App\Http\Controllers\Web\Administrator\PermissionController;
use App\Http\Controllers\Web\Administrator\RoleController;
use App\Http\Controllers\Web\Administrator\UserController;
use Illuminate\Support\Facades\Route;


Route::resource('/roles', RoleController::class)->names('roles');
Route::post('/roles/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');

Route::resource('/permissions', PermissionController::class)->names('permissions');
Route::resource('/users', UserController::class)->names('users');
Route::resource('/clubs', ClubController::class)->names('clubs');

Route::post('/change-club', [ClubController::class, 'changeClub'])->name('change.club');

// Accesos app móvil
Route::resource('/member-access', MemberAccessController::class)->only(['index', 'store', 'destroy'])->names('member-access')->parameters(['member-access' => 'member']);
