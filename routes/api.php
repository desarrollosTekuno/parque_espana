<?php

use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\FeedbackTicketMobileController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LockerApiController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\BusinessAdController;
use App\Http\Controllers\Api\V1\ReservationGuestController;
use App\Http\Controllers\Api\V1\SurveyController;
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

    // Surveys (encuestas para socios — agrupadas por club)
    Route::middleware('auth:sanctum')->prefix('clubs/{club}')->group(function () {
        Route::get('/surveys', [SurveyController::class, 'index']);                     // Encuestas activas pendientes
        Route::get('/surveys/{survey}', [SurveyController::class, 'show']);             // Detalle con preguntas
        Route::post('/surveys/{survey}/responses', [SurveyController::class, 'store']); // Enviar respuestas

        // Feedback (tickets de quejas y sugerencias de cada usuario)
        Route::get('/feedback/tickets', [FeedbackTicketMobileController::class, 'index']);
        Route::post('/feedback/tickets', [FeedbackTicketMobileController::class, 'store']);
        Route::patch('/feedback/tickets/{ticket}/cancel', [FeedbackTicketMobileController::class, 'cancel']);
    });

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
