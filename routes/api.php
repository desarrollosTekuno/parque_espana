<?php

use App\Http\Controllers\Api\V1\AccountStatementController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\FamilyMembersController;
use App\Http\Controllers\Api\V1\MyMembersController;
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
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\LockerApiController;
use App\Http\Controllers\Api\V1\MemberDocumentController;
use App\Http\Controllers\Api\V1\ChargePaymentController;
use App\Http\Controllers\Api\V1\ConektaConfigController;
use App\Http\Controllers\Api\V1\PaymentSourceController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\GardenReservationController;
use App\Http\Controllers\Api\V1\BusinessAdController;
use App\Http\Controllers\Api\V1\BusinessCategoryController;
use App\Http\Controllers\Api\V1\ClubContactInfoController;
use App\Http\Controllers\Api\V1\ReservationGuestController;
use App\Http\Controllers\Api\V1\SurveyController;
use App\Http\Controllers\Api\V1\WebsiteApiController;
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

    // Garden Reservations
    Route::get('garden-reservations/catalog', [GardenReservationController::class, 'catalog'])->middleware('auth:sanctum');
    Route::post('garden-reservations', [GardenReservationController::class, 'store'])->middleware('auth:sanctum');

    // Amenities
    Route::get('/amenities/{amenityResource}/available-slots', [AmenityController::class, 'availableSlots'])->middleware('auth:sanctum');
    Route::get('/amenities/{amenity}/teachers', [AmenityController::class, 'teachers'])->middleware('auth:sanctum');
    Route::get('/amenities/{amenityResource}/classes', [AmenityController::class, 'classes'])->middleware('auth:sanctum');
    Route::get('/clubs/{club}/amenities', [AmenityController::class, 'amenitiesByClub'])->middleware('auth:sanctum'); 

    // =================================== Pagina web Test ================================
    Route::get('/clubs/{club}/website/carousel', [WebsiteApiController::class, 'carousel']);
    Route::get('/clubs/{club}/website/home-cards', [WebsiteApiController::class, 'homeCards']);
    Route::get('/clubs/{club}/website/membership-prices', [WebsiteApiController::class, 'membershipPrices']);
    Route::get('/clubs/{club}/website/virtual-tour', [WebsiteApiController::class, 'virtualTour']);
    Route::get('/clubs/{club}/website/events', [WebsiteApiController::class, 'events']);

    // Business Ads
    // Enviar solicitud de promoción desde la app
    Route::post('/business-ads', [BusinessAdController::class, 'store'])->middleware('auth:sanctum');

    // Agrupadas por club
    Route::middleware('auth:sanctum')->prefix('clubs/{club}')->group(function () {
        // Mostrar categorías de negocios en la pantalla principal de la app
        Route::get('/business-categories', [BusinessCategoryController::class, 'index']);

        // Información de contacto del club (pantallas "Contacto" y "Mapa" de la app)
        Route::get('/contact-info', [ClubContactInfoController::class, 'show']);

        // Mostrar negocios publicados por categoría en la app
        Route::get('/business-ads', [BusinessAdController::class, 'index']);

        // Mostrar detalle de un negocio publicado
        Route::get('/business-ads/{businessAd}', [BusinessAdController::class, 'show']);

        // Encuestas
        Route::get('/surveys', [SurveyController::class, 'index']);                      // Encuestas activas pendientes
        Route::get('/surveys/{survey}', [SurveyController::class, 'show']);              // Detalle con preguntas
        Route::post('/surveys/{survey}/responses', [SurveyController::class, 'store']); // Enviar respuestas

        // Feedback (tickets de quejas y sugerencias de cada usuario)
        Route::get('/feedback/options',                          [FeedbackTicketMobileController::class, 'options']);
        Route::get('/feedback/tickets',                          [FeedbackTicketMobileController::class, 'index']);
        Route::post('/feedback/tickets',                         [FeedbackTicketMobileController::class, 'store']);
        Route::get('/feedback/tickets/{ticket}',                 [FeedbackTicketMobileController::class, 'show']);
        Route::post('/feedback/tickets/{ticket}/comments',       [FeedbackTicketMobileController::class, 'comment']);
        Route::patch('/feedback/tickets/{ticket}/cancel',        [FeedbackTicketMobileController::class, 'cancel']);

        // Estado de cuenta (solo socio titular)
        Route::get('/account-statement', [AccountStatementController::class, 'show']);

        // APIs para pagos de la app movil.
        Route::prefix('payments')->group(function () {
            Route::get('/pending', [PaymentController::class, 'pending']);
            Route::get('/history', [PaymentController::class, 'history']);
            Route::get('/monthly-fees', [PaymentController::class, 'monthlyFees']);
            Route::get('/{payment}/receipt', [PaymentController::class, 'receipt']);
            Route::get('/{payment}', [PaymentController::class, 'show']);
        });

        // Integrantes de membresía familiar (solo socio titular)
        Route::get('/family-members', [FamilyMembersController::class, 'index']);

        // Credenciales de acceso — todos los integrantes de la cuenta con su access_code
        Route::get('/my-members', [MyMembersController::class, 'index']);

        // SPEI — generar CLABE y consultar estado de orden
        Route::post('/spei-payment', [SpeiPaymentController::class, 'store']);
        Route::get('/spei-payment/{speiOrder}', [SpeiPaymentController::class, 'show']);

        // Llave pública de Conekta de este club (para tokenizar del lado del cliente)
        Route::get('/conekta-public-key', [ConektaConfigController::class, 'publicKey']);

        // Tarjetas guardadas (domiciliación) — cada club es una cuenta Conekta
        // independiente, por eso las tarjetas están scoped por club
        Route::prefix('payment-sources')->group(function () {
            Route::get('/',           [PaymentSourceController::class, 'index']);
            Route::post('/',          [PaymentSourceController::class, 'store']);
            Route::delete('/{source}', [PaymentSourceController::class, 'destroy']);
            Route::patch('/{source}/set-default', [PaymentSourceController::class, 'setDefault']);
        });
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
    Route::get('/lockers/pricing', [LockerApiController::class, 'pricing'])->middleware('auth:sanctum');
    Route::get('/lockers/mine', [LockerApiController::class, 'mine'])->middleware('auth:sanctum');
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

});

// Get user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json($request->user());

// Route::post('reservations', [ReservationController::class, 'store'])->middleware('auth:sanctum');

});
