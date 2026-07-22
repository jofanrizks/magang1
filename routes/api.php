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
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;

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

Route::middleware(['auth:api', 'account.active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/me', [LoginController::class, 'me']);
    Route::post('/account/change-required-password', [AccountController::class, 'changeRequiredPassword']);

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/setting', [SettingController::class, 'update']);
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/admin/services', [AdminServiceController::class, 'index']);
        Route::get('/admin/services/{id}', [AdminServiceController::class, 'show']);
        Route::put('/admin/services/{id}', [AdminServiceController::class, 'update']);

        // User Management
        Route::get('/users/pending', [UserController::class, 'pendingUsers']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::post('/users/{id}/send-otp', [UserController::class, 'sendOtp'])
            ->middleware('throttle:5,1');
        Route::post('/users/{id}/reject', [UserController::class, 'rejectUser']);
        Route::post('/users/{id}/disable', [UserController::class, 'disableUser']);
        Route::post('/users/{id}/enable', [UserController::class, 'enableUser']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::get('/getallusers', [UserController::class, 'getAllUsers']);
        Route::get('/getApprovedUsers', [UserController::class, 'getApprovedUsers']);
        Route::get('/dashboard', [UserController::class, 'dashboard']);
        Route::get('/users/{id}/log', [UserController::class, 'logUser']);
        Route::get('/groups/{group}', [GroupController::class, 'show']);
        Route::post('/admin/group-files', [GroupFileController::class, 'adminStore']);
        Route::delete('/admin/group-files/{id}', [GroupFileController::class, 'adminDestroy']);
        Route::patch('/admin/group-files/{id}/move', [GroupFileController::class, 'move']);

        Route::post('/banner', [BannerController::class, 'store']);
        Route::delete('/banner/{id}', [BannerController::class, 'destroy']);
    });

    Route::middleware('role:super_admin,admin,user,viewer')->group(function () {
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{id}', [ServiceController::class, 'show']);
        Route::get('/group-files', [GroupFileController::class, 'index']);
        Route::post('/group-files/{groupFile}/replace', [GroupFileController::class, 'replace']);
        Route::get('/group-files/{groupFile}/download', [GroupFileController::class, 'download']);
    });

    Route::middleware('role:user')->group(function () {
        Route::post('/account/disable/send-otp', [AccountController::class, 'sendDisableOtp'])
            ->middleware('throttle:3,1');
        Route::post('/account/disable', [AccountController::class, 'disableAccount'])
            ->middleware('throttle:5,1');

        // Group Files
        Route::post('/group-files', [GroupFileController::class, 'store']);
        Route::delete('/group-files/{id}', [GroupFileController::class, 'destroy']);
    });
});
