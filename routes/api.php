<?php

use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LockerApiController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\BusinessAdController;
use App\Http\Controllers\Api\V1\ReservationGuestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Api V1
Route::prefix('v1')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

    // Reservations
    Route::apiResource('reservations', ReservationController::class)->only(['store', 'destroy', 'update'])->middleware('auth:sanctum');
    Route::get('my-reservations', [ReservationController::class, 'myReservations'])->middleware('auth:sanctum');

    // GuestsList
    Route::apiResource('guests-list', ReservationGuestController::class)->only(['store'])->middleware('auth:sanctum');

    // Amenities
    Route::get('/amenities/{amenityResource}/available-slots', [AmenityController::class, 'availableSlots'])->middleware('auth:sanctum');
    Route::get('/clubs/{club}/amenities', [AmenityController::class, 'amenitiesByClub'])->middleware('auth:sanctum');

    // Business Ads
    Route::post('/business-ads', [BusinessAdController::class, 'store'])->middleware('auth:sanctum');

    // Lockers
    Route::get('/lockers/index', [LockerApiController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/lockers/members', [LockerApiController::class, 'membersAvailable'])->middleware('auth:sanctum');
    Route::post('/lockers/assign', [LockerApiController::class, 'assign'])->middleware('auth:sanctum');

});

// Get user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json($request->user());

// Route::post('reservations', [ReservationController::class, 'store'])->middleware('auth:sanctum');

});
