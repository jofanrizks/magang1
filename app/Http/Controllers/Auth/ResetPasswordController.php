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
                'success' => true,
                'message' => 'Jika NIK terdaftar, OTP akan dikirim ke WhatsApp'
            ]);
        }

        $otp = $otpService->generate(
            $user->id,
            'reset_password'
        );

        $sent = $whatsappService->send(
            $user->telp,
            "RESET PASSWORD\n\nKode OTP Anda: {$otp->plain_code}\n\nOTP berlaku selama 5 menit."
        );

        if (!$sent) {
            $otp->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP gagal dikirim'
            ], 502);
        }

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
            'otp' => 'required|digits:6',
            'password' => 'required|min:6|confirmed'
        ]);

        $user = User::where(
            'nik',
            $request->nik
        )->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'NIK atau OTP tidak valid atau expired'
            ], 400);
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
            ),
            'must_change_password' => false,
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
