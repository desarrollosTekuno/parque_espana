<?php

use App\Http\Controllers\Web\AdminClub\AmenityController;
use App\Http\Controllers\Web\AdminClub\ReservationController;
use App\Http\Controllers\Web\AdminClub\AmenityScheduleController;
use App\Http\Controllers\Web\Administrator\UserController;
use Illuminate\Support\Facades\Route;


Route::resource('/amenities', AmenityController::class)->names('amenities');
Route::resource('/amenitySchedule', AmenityScheduleController::class)->names('amenitySchedule');

Route::resource('/reservations', ReservationController::class)->only(['index', 'update'])->names('reservations');
