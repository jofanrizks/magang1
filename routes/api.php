<?php


use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
















// Route::get('/testing', function () {
//     return response()->json([
//         'success' => true,
//         'message' => 'Testing berhasil',
//         'data' => []
//     ], 200);
// });