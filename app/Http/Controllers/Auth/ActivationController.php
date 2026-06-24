<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;

class ActivationController extends Controller
{
    public function activate(
        Request $request,
        OtpService $otpService
    )
    {
        $request->validate([
            'nik' => 'required',
            'otp' => 'required'
        ]);

        $user = User::where(
            'nik',
            $request->nik
        )->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $valid = $otpService->verify(
            $user->id,
            $request->otp,
            'activation'
        );

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid'
            ], 400);
        }

        $user->update([
            'approval' => 'approved',
            'sts' => 'aktif'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diaktifkan'
        ]);
    }
}