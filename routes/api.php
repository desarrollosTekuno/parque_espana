<?php

use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\ReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Api V1
Route::prefix('v1')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    Route::apiResource('reservations', ReservationController::class)->only(['store', 'destroy'])->middleware('auth:sanctum');
    Route::get('my-reservations', [ReservationController::class, 'myReservations'])->middleware('auth:sanctum');
    Route::get('/amenities/{amenityResource}/available-slots', [ReservationController::class, 'availableSlots'])->middleware('auth:sanctum');
    // Route::apiResource('amenities', AmenityController::class)->only(['index'])->middleware('auth:sanctum');
    Route::get('/clubs/{club}/amenities', [AmenityController::class, 'amenitiesByClub'])->middleware('auth:sanctum');
});

// Get user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json($request->user());

// Route::post('reservations', [ReservationController::class, 'store'])->middleware('auth:sanctum');

});
