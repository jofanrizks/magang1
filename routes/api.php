<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\Account\GroupFileController;

//PUBLIC
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/activate', [ActivationController::class, 'activate'])
    ->middleware('throttle:5,1');

Route::post('/forgot-password/send-otp', [ResetPasswordController::class, 'sendOtp'])
    ->middleware('throttle:3,1');
Route::post('/forgot-password/reset', [ResetPasswordController::class, 'resetPassword'])
    ->middleware('throttle:5,1');
Route::post('/account/reactivate/send-otp', [AccountController::class, 'sendReactivateOtp'])
    ->middleware('throttle:3,1');
Route::post('/account/reactivate', [AccountController::class, 'reactivateAccount'])
    ->middleware('throttle:5,1');
Route::get('/banner', [BannerController::class, 'index']);
Route::get('/setting', [SettingController::class, 'index']);



Route::get('/groups', [GroupController::class, 'index']);
//PROTECT

Route::middleware(['auth:api', 'admin'])->group(function () {

    // User Management
    Route::get('/users/pending', [UserController::class, 'pendingUsers']);
    Route::post('/users/{id}/send-otp', [UserController::class, 'sendOtp'])
        ->middleware('throttle:5,1');
    Route::post('/users/{id}/reject', [UserController::class, 'rejectUser']);
    Route::post('/users/{id}/disable', [UserController::class, 'disableUser']);
    Route::post('/users/{id}/enable', [UserController::class, 'enableUser']);

    Route::get('/getallusers', [UserController::class, 'getAllUsers']);
    Route::get('/getApprovedUsers', [UserController::class, 'getApprovedUsers']);
    Route::get('/dashboard', [UserController::class, 'dashboard']);
    Route::get('/users/{id}/log', [UserController::class, 'logUser']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);

    // Setting
    
});

Route::middleware(['auth:api', 'user'])->group(function () {

    // Auth
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('/me', [LoginController::class, 'me']);

    // Account
    Route::post('/banner', [BannerController::class, 'store']);
    Route::delete('/banner/{id}', [BannerController::class, 'destroy']);
    Route::post('/setting', [SettingController::class, 'update']);
    
    Route::post('/account/disable/send-otp', [AccountController::class, 'sendDisableOtp'])
        ->middleware('throttle:3,1');
    Route::post('/account/disable', [AccountController::class, 'disableAccount'])
        ->middleware('throttle:5,1');

    // Group Files
    Route::get('/group-files', [GroupFileController::class, 'index']);
    Route::post('/group-files', [GroupFileController::class, 'store']);
    Route::delete('/group-files/{id}', [GroupFileController::class, 'destroy']);
});
