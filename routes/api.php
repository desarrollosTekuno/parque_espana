<?php

use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('login', [
  LoginController::class,
  'login'
]);

// Route::post('reservations', [ReservationController::class, 'store'])->middleware('auth:sanctum');

Route::apiResource('reservations', ReservationController::class)->only(['store'])->middleware('auth:sanctum');
