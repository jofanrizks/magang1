<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;

class ResetPasswordController extends Controller
{
    public function sendOtp(
        Request $request,
        OtpService $otpService,
        WhatsappService $whatsappService
    )
    {
        $request->validate([
            'nik' => 'required'
        ]);

        $user = User::where('nik', $request->nik)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $otp = $otpService->generate(
            $user->id,
            'reset_password'
        );

        $whatsappService->send(
            $user->telp,
            "RESET PASSWORD\n\nKode OTP Anda: {$otp->code}\n\nOTP berlaku selama 5 menit."
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil dikirim ke WhatsApp'
        ]);
    }
    public function resetPassword(
        Request $request,
        OtpService $otpService
    )
    {
        $request->validate([
            'nik' => 'required',
            'otp' => 'required',
            'password' => 'required|min:6|confirmed'
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
            'reset_password'
        );

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid atau expired'
            ], 400);
        }

        $user->update([
            'password' => Hash::make(
                $request->password
            )
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Reset Password',
            'description' => 'User reset password via OTP',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah'
        ]);
    }
}