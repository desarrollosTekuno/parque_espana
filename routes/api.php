<?php

use App\Http\Controllers\Api\V1\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    // return $request->user();
    // return request user permissions
    return $request->user()->getAllPermissions();
})->middleware('auth:sanctum');

Route::post('login', [
  LoginController::class,
  'login'
]);
