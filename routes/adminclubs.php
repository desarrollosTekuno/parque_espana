<?php

use App\Http\Controllers\Web\AdminClub\AmenityController;
use App\Http\Controllers\Web\AdminClub\ReservationController;
use App\Http\Controllers\Web\Administrator\UserController;
use Illuminate\Support\Facades\Route;


Route::resource('/amenities', AmenityController::class)->names('amenities');

Route::resource('/reservations', ReservationController::class)->only(['index'])->names('reservations');
