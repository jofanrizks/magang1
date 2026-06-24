<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\UserController;

//PUBLIC
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::post('/activate', [ActivationController::class, 'activate']);

Route::post('/forgot-password/send-otp', [ResetPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/reset', [ResetPasswordController::class, 'resetPassword']);

//PROTECT

Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return auth()->user();
    });

    // User Management
    Route::get('/users/pending', [UserController::class, 'pendingUsers']);
    Route::post('/users/{id}/send-otp', [UserController::class, 'sendOtp']);
    Route::post('/users/{id}/reject', [UserController::class, 'rejectUser']);

    Route::get('/getallusers', [UserController::class, 'getAllUsers']);
    Route::get('/getApprovedUsers', [UserController::class, 'getApprovedUsers']);

});