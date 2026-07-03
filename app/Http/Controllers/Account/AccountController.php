<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\OtpService;
use App\Services\WhatsappService;
use App\Models\ActivityLog;

class AccountController extends Controller
{

    public function sendDisableOtp(
        Request $request,
        OtpService $otpService,
        WhatsappService $whatsappService
    ) {

        $request->validate([
            'password' => 'required'
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {

            return response()->json([
                'success' => false,
                'message' => 'Password salah'
            ], 401);

        }

        $otp = $otpService->generate(
            $user->id,
            'disable_account'
        );

        $whatsappService->send(
            $user->telp,
            "Kode OTP untuk menonaktifkan akun Anda adalah {$otp->code}"
        );
        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Request Disable OTP',
            'description' => 'Pengguna meminta OTP untuk menonaktifkan akun',
            'ip_address' => $request->ip(),
        ]);
        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil dikirim ke WhatsApp'
        ]);
    }

    public function disableAccount(
        Request $request,
        OtpService $otpService
    ) {

        $request->validate([
            'otp' => 'required|digits:4'
        ]);

        $user = auth()->user();

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);

        }

        $valid = $otpService->verify(
            $user->id,
            $request->otp,
            'disable_account'
        );

        if (!$valid) {

            return response()->json([
                'success' => false,
                'message' => 'OTP salah atau sudah expired'
            ], 400);

        }

        $user->update([
            'sts' => 'disabled',
            'tgldisabled' => now()
        ]);
        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Account Disabled',
            'description' => 'Pengguna menonaktifkan akun',
            'ip_address' => $request->ip(),

        ]);

        auth()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dinonaktifkan'
        ]);
    }
}