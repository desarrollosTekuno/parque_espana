<?php

use App\Http\Controllers\Web\AdminClub\SurveyController;
use App\Http\Controllers\Web\AdminClub\MemberController;
use App\Http\Controllers\Web\AdminClub\BillingController;
use App\Http\Controllers\Web\AdminClub\AmenityController;
use App\Http\Controllers\Web\AdminClub\BusinessAdController;
use App\Http\Controllers\Web\AdminClub\ReservationController;
use App\Http\Controllers\Web\AdminClub\AnnouncementController;
use App\Http\Controllers\Web\AdminClub\BlockedPeriodController;
use App\Http\Controllers\Web\AdminClub\SystemVariableController;
use App\Http\Controllers\Web\AdminClub\AmenityScheduleController;
use App\Http\Controllers\Web\AdminClub\BusinessCategoryController;
use App\Http\Controllers\Web\AdminClub\BillingConceptController;
use App\Http\Controllers\Web\AdminClub\InterclubPackageRuleController;
use App\Http\Controllers\Web\AdminClub\PricingRuleController;
use App\Http\Controllers\Web\AdminClub\AmenityResourceController;
use App\Http\Controllers\Web\AdminClub\ReservationGuestListController;
use App\Http\Controllers\Web\AdminClub\LockerAssignmentController;
use App\Http\Controllers\Web\AdminClub\LockerController;
use Illuminate\Support\Facades\Route;

// amenities
Route::resource('/amenities', AmenityController::class)->names('amenities');
Route::resource('/amenityResource', AmenityResourceController::class)->names('amenityResource');
Route::resource('/amenitySchedule', AmenityScheduleController::class)->names('amenitySchedule');
Route::resource('/blockedPeriods', BlockedPeriodController::class)->names('blockedPeriods');

Route::resource('/reservations', ReservationController::class)->only(['index', 'update'])->names('reservations');
Route::resource('/system-variables', SystemVariableController::class)->only(['index', 'store', 'update', 'destroy'])->names('system-variables');

Route::resource('/guest-lists', ReservationGuestListController::class)->only(['index', 'update'])->names('guest-lists');
Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
Route::post('/billing/payments', [BillingController::class, 'storePayment'])->name('billing.payments.store');
Route::resource('/billing-concepts', BillingConceptController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('billing-concepts');
Route::resource('/pricing-rules', PricingRuleController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('pricing-rules');
Route::resource('/interclub-package-rules', InterclubPackageRuleController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('interclub-package-rules');

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

// business_ads categories
Route::prefix('business-categories')->name('business-categories.')->group(function () {
    Route::get('/', [BusinessCategoryController::class, 'index'])->name('index');
    Route::post('/', [BusinessCategoryController::class, 'store'])->name('store');
    Route::put('{id}', [BusinessCategoryController::class, 'update'])->name('update');
    Route::delete('{id}', [BusinessCategoryController::class, 'destroy'])->name('destroy');
});

// surveys
Route::resource('/surveys', SurveyController::class)->names('surveys');

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


// Lockers
Route::get('/members/{accountId}/lockers/create', [LockerAssignmentController::class, 'create'])
    ->name('members.lockers.create');
Route::get('/lockers/assigned-by-account', [LockerController::class, 'assignedByAccount'])
    ->name('lockers.assigned.by.account');
Route::get('/lockers/available', [LockerController::class, 'available'])
    ->name('lockers.available');
Route::post('/lockers', [LockerAssignmentController::class, 'reserve'])
        ->name('members.lockers.reserve');
