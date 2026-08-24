<?php

use App\Http\Controllers\Web\AdminClub\ActController;
use App\Http\Controllers\Web\AdminClub\SurveyController;
use App\Http\Controllers\Web\AdminClub\MemberController;
use App\Http\Controllers\Web\AdminClub\BillingController;
use App\Http\Controllers\Web\AdminClub\AmenityController;
use App\Http\Controllers\Web\AdminClub\BusinessAdController;
use App\Http\Controllers\Web\AdminClub\PhysicalAdController;
use App\Http\Controllers\Web\AdminClub\PhysicalAdSizeController;
use App\Http\Controllers\Web\AdminClub\ReservationController;
use App\Http\Controllers\Web\AdminClub\AnnouncementController;
use App\Http\Controllers\Web\AdminClub\BlockedPeriodController;
use App\Http\Controllers\Web\AdminClub\SystemVariableController;
use App\Http\Controllers\Web\AdminClub\AppVariableController;
use App\Http\Controllers\Web\AdminClub\AmenityScheduleController;
use App\Http\Controllers\Web\AdminClub\BusinessCategoryController;
use App\Http\Controllers\Web\AdminClub\BillingConceptController;
use App\Http\Controllers\Web\AdminClub\InterclubPackageRuleController;
use App\Http\Controllers\Web\AdminClub\PricingRuleController;
use App\Http\Controllers\Web\AdminClub\FeeScheduleController;
use App\Http\Controllers\Web\AdminClub\AmenityResourceController;
use App\Http\Controllers\Web\AdminClub\Feedback\FeedbackCategoryController;
use App\Http\Controllers\Web\AdminClub\Feedback\FeedbackController;
use App\Http\Controllers\Web\AdminClub\Feedback\FeedbackManagementController;
use App\Http\Controllers\Web\AdminClub\Feedback\FeedbackPriorityController;
use App\Http\Controllers\Web\AdminClub\Feedback\FeedbackStatusController;
use App\Http\Controllers\Web\AdminClub\Feedback\FeedbackTicketTypeController;
use App\Http\Controllers\Web\AdminClub\GuestListVariableController;
use App\Http\Controllers\Web\AdminClub\ReservationGuestListController;
use App\Http\Controllers\Web\AdminClub\SurveyResultController;
use App\Http\Controllers\Web\AdminClub\AccountCancellationController;
use App\Http\Controllers\Web\AdminClub\AccountReactivationController;
use App\Http\Controllers\Web\AdminClub\AgeTransitionController;
use App\Http\Controllers\Web\AdminClub\CancellationHistoryController;
use App\Http\Controllers\Web\AdminClub\MemberDocumentController;
use App\Http\Controllers\Web\AdminClub\LockerAssignmentController;
use App\Http\Controllers\Web\AdminClub\LockerController;
use App\Http\Controllers\Web\AdminClub\CashCutController;
use App\Http\Controllers\Web\AdminClub\CollectionController;
use App\Http\Controllers\Web\AdminClub\GlobalCashCutController;
use App\Http\Controllers\Web\AdminClub\DocumentTypeController;
use App\Http\Controllers\Web\Administrator\EmailConfigController;
use App\Http\Controllers\Web\Administrator\EmailNotificationController;
use App\Http\Controllers\Web\Administrator\NotificationController;
use App\Http\Controllers\Web\AdminClub\FileController;
use App\Http\Controllers\Web\AdminClub\CafeteriaVisitController;
use App\Http\Controllers\Web\AdminClub\DayPassController;
use App\Http\Controllers\Web\AdminClub\GuestListPaymentController;
use App\Http\Controllers\Web\AdminClub\MembershipTypeController;
use App\Http\Controllers\Web\AdminClub\PaymentMethodController;
use App\Http\Controllers\Web\AdminClub\TicketController;
use App\Http\Controllers\Web\AdminClub\PaymentCancellationController;
use App\Http\Controllers\Web\AdminClub\LockerAssignmentHistoryController;
use App\Http\Controllers\Web\AdminClub\ClinicalHistoryController;
use App\Http\Controllers\Web\AdminClub\ClubSettingsController;
use App\Http\Controllers\Web\AdminClub\CoachController;
use App\Http\Controllers\Web\AdminClub\SpecialtyController;
use App\Http\Controllers\Web\AdminClub\ClassScheduleController;
use App\Http\Controllers\Web\AdminClub\WebsiteContentController;
use App\Http\Controllers\Web\AdminClub\WebsiteContactController;
use Illuminate\Support\Facades\Route;

// amenities
Route::resource('/amenities', AmenityController::class)->names('amenities');
Route::resource('/amenityResource', AmenityResourceController::class)->names('amenityResource');
Route::post('/amenitySchedule', [AmenityScheduleController::class, 'store'])->name('amenitySchedule.store');
Route::resource('/blockedPeriods', BlockedPeriodController::class)->names('blockedPeriods');
Route::get('/amenity-resource/{resource}/calendar', [AmenityResourceController::class, 'calendar'])->name('amenityResource.calendar');

// QR de amenities (genera y descarga en una sola petición GET)
Route::get('/amenity-resource/{amenityResource}/generate-qr', [AmenityResourceController::class, 'generateQr'])->name('amenityResource.generateQr');

// reservations
Route::resource('/reservations', ReservationController::class)->only(['index', 'update', 'store'])->names('reservations');
Route::resource('/system-variables', SystemVariableController::class)->only(['index', 'store', 'update', 'destroy'])->names('system-variables');
Route::resource('/app-variables', AppVariableController::class)->only(['index', 'store', 'update', 'destroy'])->names('app-variables');
Route::get('/reservations/calendar', [ReservationController::class, 'calendar'])
    ->name('reservations.calendar');
Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])
    ->name('reservations.cancel');
Route::get('/amenity-resource/{amenityResource}/slots', [ReservationController::class, 'slots'])
    ->name('reservations.slots');

Route::resource('/guest-lists', ReservationGuestListController::class)->only(['index', 'update'])->names('guest-lists');
Route::resource('/guest-list-variables', GuestListVariableController::class)->only(['index', 'store', 'update', 'destroy']);
Route::resource('/guest-list-payments', GuestListPaymentController::class)->only(['index'])->names('guest-list-payments');

Route::get('/day-passes/members-search', [DayPassController::class, 'searchMembers'])->name('day-passes.members-search');
Route::get('/day-passes/pricing', [DayPassController::class, 'pricing'])->name('day-passes.pricing');
Route::get('/day-passes/check-incidents', [DayPassController::class, 'checkIncidents'])->name('day-passes.check-incidents');
Route::get('/day-passes/incidents',  [DayPassController::class, 'indexIncidents'])->name('day-passes.incidents.index');
Route::post('/day-passes/incidents', [DayPassController::class, 'storeIncident'])->name('day-passes.incidents.store');
Route::resource('/day-passes', DayPassController::class)->only(['index', 'store'])->names('day-passes');

// cafeteria visits
Route::get('/cafeteria-visits', [CafeteriaVisitController::class, 'index'])->name('cafeteria-visits.index');
Route::get('/cafeteria-visits/open', [CafeteriaVisitController::class, 'open'])->name('cafeteria-visits.open');
Route::post('/cafeteria-visits', [CafeteriaVisitController::class, 'store'])->name('cafeteria-visits.store');
Route::post('/cafeteria-visits/{cafeteriaVisit}/checkout', [CafeteriaVisitController::class, 'checkout'])->name('cafeteria-visits.checkout');

Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
Route::get('/billing/reports/income', [BillingController::class, 'incomeReport'])->name('billing.reports.income');
Route::get('/billing/reports/interclub-income', [BillingController::class, 'interclubIncomeReport'])->name('billing.reports.interclub-income');
Route::get('/billing/charges/export', [BillingController::class, 'exportChargesReport'])->name('billing.charges.export');
Route::get('/billing/charges', [BillingController::class, 'chargesList'])->name('billing.charges.index');
Route::post('/billing/payments', [BillingController::class, 'storePayment'])->name('billing.payments.store');
Route::get('/billing/annual-payment/preview', [BillingController::class, 'annualPaymentPreview'])->name('billing.annual-payment.preview');
Route::post('/billing/annual-payment', [BillingController::class, 'storeAnnualPayment'])->name('billing.annual-payment.store');

// collections desk (módulo de cobranza tipo caja)
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
Route::get('/collections/search', [CollectionController::class, 'search'])->name('collections.search');
Route::post('/collections/monthly-fee/resolve', [CollectionController::class, 'resolveMonthlyFeeMonths'])->name('collections.monthly-fee.resolve');
Route::post('/collections/inscription/resolve', [CollectionController::class, 'resolveInscriptionInstallments'])->name('collections.inscription.resolve');
Route::post('/collections/payment', [CollectionController::class, 'storePayment'])->name('collections.payment.store');
Route::post('/collections/annual-payment/preview', [CollectionController::class, 'previewAnnualPayment'])->name('collections.annual-payment.preview');
Route::post('/collections/notes', [CollectionController::class, 'storeNote'])->name('collections.notes.store');

// cash cuts
Route::get('/cash-cuts', [CashCutController::class, 'index'])->name('cash-cuts.index');
Route::post('/cash-cuts', [CashCutController::class, 'store'])->name('cash-cuts.store');
Route::get('/cash-cuts/{cashCut}', [CashCutController::class, 'show'])->name('cash-cuts.show');
Route::post('/cash-cuts/{cashCut}/close', [CashCutController::class, 'close'])->name('cash-cuts.close');
Route::get('/cash-cuts/{cashCut}/export', [CashCutController::class, 'export'])->name('cash-cuts.export');

// global cash cuts
Route::get('/global-cash-cuts', [GlobalCashCutController::class, 'index'])->name('global-cash-cuts.index');
Route::post('/global-cash-cuts', [GlobalCashCutController::class, 'store'])->name('global-cash-cuts.store');
Route::get('/global-cash-cuts/{globalCashCut}', [GlobalCashCutController::class, 'show'])->name('global-cash-cuts.show');
Route::post('/global-cash-cuts/{globalCashCut}/close', [GlobalCashCutController::class, 'close'])->name('global-cash-cuts.close');
Route::get('/global-cash-cuts/{globalCashCut}/export', [GlobalCashCutController::class, 'export'])->name('global-cash-cuts.export');
Route::resource('/billing-concepts', BillingConceptController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('billing-concepts');
Route::resource('/payment-methods', PaymentMethodController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('payment-methods');
Route::post('/payment-methods/{paymentMethod}/toggle-club', [PaymentMethodController::class, 'toggleClub'])
    ->name('payment-methods.toggle-club');
Route::put('/payment-methods/{paymentMethod}/club-config', [PaymentMethodController::class, 'updateClubConfig'])
    ->name('payment-methods.update-club-config');
Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
Route::get('/tickets/{payment}/data', [TicketController::class, 'data'])->name('tickets.data');

// Cancelación de pagos (p. ej. cheque rebotado)
Route::get('/payments/{payment}/cancel/create', [PaymentCancellationController::class, 'create'])
    ->name('payments.cancel.create');
Route::post('/payments/{payment}/cancel', [PaymentCancellationController::class, 'store'])
    ->name('payments.cancel.store');
Route::get('/payments/group/{paymentGroupId}/cancel/create', [PaymentCancellationController::class, 'groupCreate'])
    ->name('payments.cancel-group.create');
Route::get('/payments/group/{paymentGroupId}/summary', [PaymentCancellationController::class, 'groupSummary'])
    ->name('payments.cancel-group.summary');
Route::post('/payments/group/{paymentGroupId}/cancel', [PaymentCancellationController::class, 'groupStore'])
    ->name('payments.cancel-group.store');
Route::resource('/pricing-rules', PricingRuleController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('pricing-rules');
Route::resource('/membership-types', MembershipTypeController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('membership-types');
Route::resource('/document-types', DocumentTypeController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('document-types');
Route::resource('/interclub-package-rules', InterclubPackageRuleController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('interclub-package-rules');
Route::get('/fee-schedules', [FeeScheduleController::class, 'index'])->name('fee-schedules.index');
Route::post('/fee-schedules', [FeeScheduleController::class, 'store'])->name('fee-schedules.store');
Route::get('/fee-schedules/preview', [FeeScheduleController::class, 'preview'])->name('fee-schedules.preview');
Route::post('/fee-schedules/annual-discount-rules', [FeeScheduleController::class, 'storeAnnualDiscountRule'])
    ->name('fee-schedules.annual-discount-rules.store');
Route::put('/fee-schedules/annual-discount-rules/{annualDiscountRule}', [FeeScheduleController::class, 'updateAnnualDiscountRule'])
    ->name('fee-schedules.annual-discount-rules.update');
Route::delete('/fee-schedules/annual-discount-rules/{annualDiscountRule}', [FeeScheduleController::class, 'destroyAnnualDiscountRule'])
    ->name('fee-schedules.annual-discount-rules.destroy');

Route::resource('/email-configs', EmailConfigController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('email-configs');

Route::resource('/notifications', NotificationController::class)->only(['index', 'store', 'update', 'destroy'])->names('notifications');
Route::get('/notifications/members', [NotificationController::class, 'getMembers'])->name('notifications.members');
Route::get('/notifications/recipients-preview', [NotificationController::class, 'recipientsPreview'])->name('notifications.recipients-preview');
Route::patch('/notifications/{id}/cancel', [NotificationController::class, 'cancel'])->name('notifications.cancel');
Route::patch('/notifications/{id}/retry-push', [NotificationController::class, 'retryPush'])->name('notifications.retry-push');
Route::post('/notifications/subscribe-test-token', [NotificationController::class, 'subscribeTestToken'])->name('notifications.subscribe-test-token');
Route::get('/notifications/export', [NotificationController::class, 'export'])->name('notifications.export');

// announcements
Route::resource('/announcements', AnnouncementController::class)->names('announcements');
Route::post('announcements/gallery', [AnnouncementController::class,'storeGallery'])->name('announcements.gallery.store');
Route::delete('announcements/gallery/{image}',[AnnouncementController::class,'destroyGalleryImage'])->name('announcements.gallery.destroy');
Route::get('announcements/{announcement}/gallery', [AnnouncementController::class,'getGallery'])->name('announcements.gallery.index');

// business_ads
Route::get('/business-ads', [BusinessAdController::class, 'index'])->name('business-ads.index');
Route::post('/business-ads/{id}/approve', [BusinessAdController::class, 'approve'])->name('business-ads.approve');
Route::post('/business-ads/{id}/reject', [BusinessAdController::class, 'reject'])->name('business-ads.reject');
Route::post('/business-ads/{id}/confirm-payment', [BusinessAdController::class, 'confirmPayment'])->name('business-ads.confirm-payment');
Route::post('/business-ads/{id}/publish', [BusinessAdController::class, 'publish'])->name('business-ads.publish');
Route::delete('/business-ads/{id}', [BusinessAdController::class, 'destroy'])->name('business-ads.destroy');

// physical_ads
Route::get('/physical-ads/members-search', [PhysicalAdController::class, 'searchMembers'])->name('physical-ads.members-search');
Route::post('/physical-ads', [PhysicalAdController::class, 'store'])->name('physical-ads.store');

// physical_ad_sizes (catálogo de tamaños)
Route::resource('/physical-ad-sizes', PhysicalAdSizeController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('physical-ad-sizes');

// business_ads categories
Route::prefix('business-categories')->name('business-categories.')->group(function () {
    Route::get('/', [BusinessCategoryController::class, 'index'])->name('index');
    Route::post('/', [BusinessCategoryController::class, 'store'])->name('store');
    Route::post('{id}', [BusinessCategoryController::class, 'update'])->name('update');
    Route::delete('{id}', [BusinessCategoryController::class, 'destroy'])->name('destroy');
});

// surveys
Route::resource('/surveys', SurveyController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->names('surveys');
Route::post('/surveys/{survey}/questions', [SurveyController::class, 'storeQuestion'])->name('surveys.questions.store');
Route::put('/surveys/{survey}/questions/{question}', [SurveyController::class, 'updateQuestion'])->name('surveys.questions.update');
Route::delete('/surveys/{survey}/questions/{question}', [SurveyController::class, 'destroyQuestion'])->name('surveys.questions.destroy');
Route::post('/surveys/{survey}/questions/reorder', [SurveyController::class, 'reorderQuestions'])->name('surveys.questions.reorder');
Route::get('/surveys/{survey}/results', [SurveyResultController::class, 'index'])->name('surveys.results');


// =========== FEEDBACK ============
Route::resource('/feedback-categories', FeedbackCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->names('feedback-categories');
Route::resource('/feedback', FeedbackController::class)->only(['index', 'store', 'update', 'destroy'])->names('feedback');
Route::patch('/feedback/{feedback}/cancel', [FeedbackController::class, 'cancelTicket'])->name('feedback.cancel');
Route::get('/feedback-management', [FeedbackManagementController::class, 'index'])->name('feedback-management.index');
Route::patch('/feedback-management/{feedback}', [FeedbackManagementController::class, 'update'])->name('feedback-management.update');
Route::resource('/feedback-ticket-types', FeedbackTicketTypeController::class)->only(['index', 'store', 'update', 'destroy'])->names('feedback-ticket-types');
Route::resource('/feedback-statuses', FeedbackStatusController::class)->only(['index', 'store', 'update', 'destroy'])->names('feedback-statuses');
Route::resource('/feedback-priorities', FeedbackPriorityController::class)->only(['index', 'store', 'update', 'destroy'])->names('feedback-priorities');

// members
Route::get('/members/{membership}/additional-membership/create', [MemberController::class, 'createAdditionalMembership'])
    ->name('members.additional-membership.create');
Route::get('/members/pricing-preview', [MemberController::class, 'pricingPreview'])
    ->name('members.pricing-preview');
Route::get('/members/location-catalogs/states', [MemberController::class, 'locationStates'])
    ->name('members.location-catalogs.states');
Route::get('/members/location-catalogs/cities', [MemberController::class, 'locationCities'])
    ->name('members.location-catalogs.cities');
Route::get('/members/{membership}/manage', [MemberController::class, 'show'])
    ->name('members.manage.show');
Route::patch('/members/{membership}/internal-account-number', [MemberController::class, 'updateInternalAccountNumber'])
    ->name('members.internal-account-number.update');
Route::get('/members/{membership}/history', [MemberController::class, 'membershipHistory'])
    ->name('members.manage.history');
Route::get('/members/{membership}/billing/charges', [BillingController::class, 'accountCharges'])
    ->name('members.billing.charges');
Route::post('/members/{membership}/documents', [MemberController::class, 'storeDocument'])
    ->name('members.documents.store');
Route::post('/members/{membership}/absence-permits', [MemberController::class, 'storeAbsencePermit'])
    ->name('members.absence-permits.store');
Route::patch('/members/{membership}/absence-permits/{absencePermit}/cancel', [MemberController::class, 'cancelAbsencePermit'])
    ->name('members.absence-permits.cancel');
Route::get('/members/{membership}/transition/create', [MemberController::class, 'createMembershipTransition'])
    ->name('members.transition.create');
Route::get('/members/{membership}/change-holder', [MemberController::class, 'createChangePrimaryHolder'])
    ->name('members.change-holder.create');
Route::patch('/members/{membership}/change-holder', [MemberController::class, 'updatePrimaryHolder'])
    ->name('members.change-holder.update');
Route::get('/members/{membership}/family-members/create', [MemberController::class, 'createFamilyMember'])
    ->name('members.family-members.create');
Route::post('/members/{membership}/family-members', [MemberController::class, 'storeFamilyMember'])
    ->name('members.family-members.store');
Route::get('/members/{membership}/separation/create', [MemberController::class, 'createMemberSeparation'])
    ->name('members.separation.create');
Route::post('/members/{membership}/separation', [MemberController::class, 'storeMemberSeparation'])
    ->name('members.separation.store');
Route::get('/members/{membership}/member/{member}/edit', [MemberController::class, 'editMember'])
    ->name('members.member.edit');
Route::put('/members/{membership}/member/{member}', [MemberController::class, 'updateMember'])
    ->name('members.member.update');
Route::resource('/members', MemberController::class)->only(['index', 'create', 'store', 'edit', 'update'])->names('members');

// Documentos
Route::get('/member-documents/{document}/url', [MemberDocumentController::class, 'temporaryUrl'])
    ->name('member-documents.url');

// Baja de cuenta
Route::get('/members/{membership}/cancel/create', [AccountCancellationController::class, 'create'])
    ->name('members.cancel.create');
Route::post('/members/{membership}/cancel', [AccountCancellationController::class, 'store'])
    ->name('members.cancel.store');

// Reactivación de cuenta
Route::get('/members/{membership}/reactivate/create', [AccountReactivationController::class, 'create'])
    ->name('members.reactivate.create');
Route::post('/members/{membership}/reactivate', [AccountReactivationController::class, 'store'])
    ->name('members.reactivate.store');

// Transiciones de edad pendientes
Route::get('/age-transitions', [AgeTransitionController::class, 'index'])
    ->name('members.age-transitions.index');
Route::get('/age-transitions/{ageTransition}/promote/create', [AgeTransitionController::class, 'createPromote'])
    ->name('members.age-transitions.promote.create');
Route::post('/age-transitions/{ageTransition}/promote', [AgeTransitionController::class, 'promote'])
    ->name('members.age-transitions.promote');
Route::patch('/age-transitions/{ageTransition}/dismiss', [AgeTransitionController::class, 'dismiss'])
    ->name('members.age-transitions.dismiss');

// Historial de bajas
Route::get('/cancellations', [CancellationHistoryController::class, 'index'])
    ->name('members.cancellations.index');
Route::get('/cancellations/export', [CancellationHistoryController::class, 'export'])
    ->name('members.cancellations.export');
Route::get('/cancellations/{account}/letter-url', [CancellationHistoryController::class, 'letterUrl'])
    ->name('members.cancellations.letter-url');


// Lockers
Route::get('/members/{accountId}/lockers/create', [LockerAssignmentController::class, 'create'])
    ->name('members.lockers.create');
Route::post('/lockers', [LockerAssignmentController::class, 'reserve'])
    ->name('members.lockers.reserve');
Route::post('lockers/change',[LockerAssignmentController::class, 'change'])
    ->name('members.lockers.change');
Route::delete('/members/lockers/{assignment}/remove', [LockerAssignmentController::class, 'remove'])
    ->name('members.lockers.remove');

Route::get('/lockers/assigned-by-account', [LockerController::class, 'assignedByAccount'])
    ->name('lockers.assigned.by.account');
Route::get('/lockers/available', [LockerController::class, 'available'])
    ->name('lockers.available');
Route::get('/lockers/quote', [LockerController::class, 'quote'])
    ->name('lockers.quote');
Route::delete('/members/lockers/{id}/cancel',[LockerController::class, 'cancel'])
        ->name('members.lockers.cancel');
Route::get('lockers/available-for-change',[LockerController::class, 'availableForChange'])
        ->name('lockers.available.for.change');
Route::get('/members/{member}/locker-history', [LockerAssignmentHistoryController::class, 'index'])
        ->name('members.lockers.history');


// Files
// Route::prefix('/files')->name('files.')->group(function () {
//     Route::get('/', [FileController::class, 'index'])->name('index');
//     Route::post('/', [FileController::class, 'store'])->name('store');
//     Route::post('/{file}', [FileController::class, 'update'])->name('update');
//     Route::delete('/{file}', [FileController::class, 'destroy'])->name('destroy');
//     Route::post('/{file}/club-file', [FileController::class, 'uploadClubFile'])->name('club-file.upload');
//     Route::delete('/{file}/club-file', [FileController::class, 'destroyClubFile'])->name('club-file.destroy');
// });

Route::resource('/files', FileController::class)->only(['index', 'store', 'update', 'destroy'])->names('files');
Route::post('files/{file}/club-file', [FileController::class, 'uploadClubFile'])->name('files.club-file.upload');
Route::delete('files/{file}/club-file', [FileController::class, 'destroyClubFile'])->name('files.club-file.destroy');
Route::post('/files/variables', [FileController::class, 'previewVariables'])->name('files.variables');

// Historia Clínica
Route::get('/members/{membership}/clinical-history', [ClinicalHistoryController::class, 'index'])
    ->name('members.clinical-history.index');
Route::put('/members/{membership}/members/{member}/clinical-history', [ClinicalHistoryController::class, 'upsert'])
    ->name('members.clinical-history.upsert');

// Club Settings
Route::get('/club-settings', [ClubSettingsController::class, 'edit'])->name('club-settings.edit');
Route::post('/club-settings', [ClubSettingsController::class, 'update'])->name('club-settings.update');

// Clases
Route::resource('/specialties', SpecialtyController::class)->only(['index', 'store', 'update', 'destroy'])->names('specialties');
Route::resource('/coaches', CoachController::class)->only(['index', 'store', 'update', 'destroy'])->names('coaches');
Route::resource('/class-schedules', ClassScheduleController::class)->only(['index', 'store', 'update', 'destroy'])->names('classSchedules');

// Página web
Route::resource('/website-content', WebsiteContentController::class)
    ->only(['index', 'store', 'destroy'])
    ->names('website-content');
Route::put('/website-content/{id}/description', [WebsiteContentController::class, 'updateDescription'])
    ->name('website-content.descriptions.update');
Route::post('/website-content/home-cards', [WebsiteContentController::class, 'storeCard'])
    ->name('website-content.cards.store');
Route::delete('/website-content/home-cards/{id}', [WebsiteContentController::class, 'destroyCard'])
    ->name('website-content.cards.destroy');
Route::post('/website-content/virtual-tour/images', [WebsiteContentController::class, 'storeVirtualTourImages'])
    ->name('website-content.virtual-tour.images.store');
Route::delete('/website-content/virtual-tour/images/{id}', [WebsiteContentController::class, 'destroyVirtualTourImage'])
    ->name('website-content.virtual-tour.images.destroy');
Route::post('/website-content/events', [WebsiteContentController::class, 'saveEvent'])
    ->name('website-content.events.save');
Route::delete('/website-content/events/{id}', [WebsiteContentController::class, 'destroyEvent'])
    ->name('website-content.events.destroy');
Route::get('/website-contacts', [WebsiteContactController::class, 'index'])
    ->name('website-contacts.index');

// Acts
Route::prefix('acts')->group(function () {
    Route::get('/{account_id}', [ActController::class, 'index'])->name('acts.index');
    Route::post('/store', [ActController::class, 'store'])->name('acts.store');
    Route::put('/{act}', [ActController::class, 'update'])->name('acts.update');
});
