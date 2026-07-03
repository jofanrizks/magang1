<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\Account\AccountController;

//PUBLIC
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::post('/activate', [ActivationController::class, 'activate']);

Route::post('/forgot-password/send-otp', [ResetPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/reset', [ResetPasswordController::class, 'resetPassword']);

//PROTECT

Route::middleware(['auth:api', 'admin'])->group(function () {

    // User Management
    Route::get('/users/pending', [UserController::class, 'pendingUsers']);
    Route::post('/users/{id}/send-otp', [UserController::class, 'sendOtp']);
    Route::post('/users/{id}/reject', [UserController::class, 'rejectUser']);
    Route::post('/users/{id}/disable', [UserController::class, 'disableUser']);
    Route::post('/users/{id}/enable', [UserController::class, 'enableUser']);

    Route::get('/getallusers', [UserController::class, 'getAllUsers']);
    Route::get('/getApprovedUsers', [UserController::class, 'getApprovedUsers']);
    Route::get('/dashboard', [UserController::class, 'dashboard']);
    Route::get('/users/{id}/log', [UserController::class, 'logUser']);

    // Setting
    Route::post('/banner', [BannerController::class, 'store']);
    Route::delete('/banner/{id}', [BannerController::class, 'destroy']);
    Route::post('/setting', [SettingController::class, 'update']);
});

Route::middleware(['auth:api', 'user'])->group(function () {

    // Auth
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return auth()->user();
    });

    // Banner & Setting
    Route::get('/banner', [BannerController::class, 'index']);
    Route::post('/banner', [BannerController::class, 'store']);
    Route::delete('/banner/{id}', [BannerController::class, 'destroy']);
   
    // Account
    Route::post('/account/disable/send-otp', [AccountController::class, 'sendDisableOtp']);
    Route::post('/account/disable', [AccountController::class, 'disableAccount']);
});