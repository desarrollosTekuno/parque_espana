<?php

use App\Http\Controllers\Api\V1\AccountStatementController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\FamilyMembersController;
use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\EmailTestController;
use App\Http\Controllers\Api\V1\FirebaseTestController;
use App\Http\Controllers\Api\V1\ConektaWebhookController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\SpeiPaymentController;
use App\Http\Controllers\Api\V1\FeedbackTicketMobileController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\MemberProfileController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\LockerApiController;
use App\Http\Controllers\Api\V1\MemberDocumentController;
use App\Http\Controllers\Api\V1\ChargePaymentController;
use App\Http\Controllers\Api\V1\PaymentSourceController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\BusinessAdController;
use App\Http\Controllers\Api\V1\ReservationGuestController;
use App\Http\Controllers\Api\V1\SurveyController;
use App\Http\Controllers\Api\V1\ClinicalHistoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Api V1
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

    // Recuperación de contraseña
    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('reset-password',  [PasswordResetController::class, 'resetPassword']);

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

    // Agrupadas por club
    Route::middleware('auth:sanctum')->prefix('clubs/{club}')->group(function () {
        // Encuestas
        Route::get('/surveys', [SurveyController::class, 'index']);                      // Encuestas activas pendientes
        Route::get('/surveys/{survey}', [SurveyController::class, 'show']);              // Detalle con preguntas
        Route::post('/surveys/{survey}/responses', [SurveyController::class, 'store']); // Enviar respuestas

        // Feedback (tickets de quejas y sugerencias de cada usuario)
        Route::get('/feedback/tickets',                          [FeedbackTicketMobileController::class, 'index']);
        Route::post('/feedback/tickets',                         [FeedbackTicketMobileController::class, 'store']);
        Route::get('/feedback/tickets/{ticket}',                 [FeedbackTicketMobileController::class, 'show']);
        Route::patch('/feedback/tickets/{ticket}/cancel',        [FeedbackTicketMobileController::class, 'cancel']);

        // Estado de cuenta (solo socio titular)
        Route::get('/account-statement', [AccountStatementController::class, 'show']);

        // Integrantes de membresía familiar (solo socio titular)
        Route::get('/family-members', [FamilyMembersController::class, 'index']);

        // SPEI — generar CLABE y consultar estado de orden
        Route::post('/spei-payment', [SpeiPaymentController::class, 'store']);
        Route::get('/spei-payment/{speiOrder}', [SpeiPaymentController::class, 'show']);
    });

    // Perfil del socio
    Route::get('/my-profile', [MemberProfileController::class, 'show'])->middleware('auth:sanctum');

    // Tokens FCM para notificaciones push
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/device-token', [DeviceTokenController::class, 'store']);
        Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);
    });

    // Documents
    Route::get('/my-documents', [MemberDocumentController::class, 'index'])->middleware('auth:sanctum');

    // Lockers
    Route::get('/lockers/index', [LockerApiController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/lockers/members', [LockerApiController::class, 'membersAvailable'])->middleware('auth:sanctum');
    Route::post('/lockers/assign', [LockerApiController::class, 'assign'])->middleware('auth:sanctum');

    // Email test
    Route::post('/email/test', [EmailTestController::class, 'send'])->middleware('auth:sanctum');

    // Firebase test
    Route::post('/firebase/test', [FirebaseTestController::class, 'send']);
    Route::get('/firebase/ping', [FirebaseTestController::class, 'ping']);

    // Check-in por QR
    Route::post('/check-in/resource/{resource}', [CheckInController::class, 'store'])
        ->middleware('auth:sanctum')
        ->name('api.check-in.store');

    // Historia clínica
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/clinical-history-list', [ClinicalHistoryController::class, 'show']);
        Route::post('/clinical-history', [ClinicalHistoryController::class, 'upsert']);
    });

    // Webhook de Conekta — sin auth, público para recibir eventos
    Route::post('/webhooks/conekta', [ConektaWebhookController::class, 'handle']);

    // Cobro con tarjeta domiciliada (Conekta)
    Route::post('/charge-payment', [ChargePaymentController::class, 'store'])->middleware('auth:sanctum');

    // Payment sources (domiciliación de tarjetas)
    Route::middleware('auth:sanctum')->prefix('payment-sources')->group(function () {
        Route::get('/',           [PaymentSourceController::class, 'index']);
        Route::post('/',          [PaymentSourceController::class, 'store']);
        Route::delete('/{source}', [PaymentSourceController::class, 'destroy']);
        Route::patch('/{source}/set-default', [PaymentSourceController::class, 'setDefault']);
    });

});

// Get user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json($request->user());

// Route::post('reservations', [ReservationController::class, 'store'])->middleware('auth:sanctum');

});
