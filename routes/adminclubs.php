<?php

use App\Htpp\Controllers\Web\AdminClub\Announcement;
use App\Http\Controllers\Web\AdminClub\AmenityController;
use App\Http\Controllers\Web\AdminClub\ReservationController;
use App\Http\Controllers\Web\AdminClub\AnnouncementController;
use App\Http\Controllers\Web\AdminClub\BlockedPeriodController;
use App\Http\Controllers\Web\AdminClub\AmenityScheduleController;
use App\Http\Controllers\Web\AdminClub\MemberController;
use App\Http\Controllers\Web\AdminClub\AmenityResourceController;
use App\Http\Controllers\Web\AdminClub\ReservationGuestListController;
use App\Http\Controllers\Web\AdminClub\SystemVariableController;
use Illuminate\Support\Facades\Route;


Route::resource('/amenities', AmenityController::class)->names('amenities');
Route::resource('/amenityResource', AmenityResourceController::class)->names('amenityResource');
Route::resource('/amenitySchedule', AmenityScheduleController::class)->names('amenitySchedule');

Route::resource('/reservations', ReservationController::class)->only(['index', 'update'])->names('reservations');
Route::resource('/system-variables', SystemVariableController::class)->only(['index', 'store', 'update', 'destroy'])->names('system-variables');

Route::resource('/guest-lists', ReservationGuestListController::class)->only(['index', 'update'])->names('guest-lists');

Route::resource('/announcements', AnnouncementController::class)->names('announcements');
Route::post('announcements/gallery', [AnnouncementController::class,'storeGallery'])->name('announcements.gallery.store');
Route::delete('announcements/gallery/{image}',[AnnouncementController::class,'destroyGalleryImage'])->name('announcements.gallery.destroy');
Route::get('announcements/{announcement}/gallery', [AnnouncementController::class,'getGallery'])->name('announcements.gallery.index');
Route::resource('/blockedPeriods', BlockedPeriodController::class)->names('blockedPeriods');

// members
Route::get('/members/{membership}/additional-membership/create', [MemberController::class, 'createAdditionalMembership'])
    ->name('members.additional-membership.create');
Route::get('/members/{membership}/manage', [MemberController::class, 'show'])
    ->name('members.manage.show');
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
Route::resource('/members', MemberController::class)->only(['index', 'create', 'store', 'edit', 'update'])->names('members');
