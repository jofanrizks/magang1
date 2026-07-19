<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Support\ActivityLogContext;

class ActivationController extends Controller
{
    public function activate(
        Request $request,
        OtpService $otpService
    )
    {
        $request->validate([
            'nik' => 'required',
            'otp' => 'required|digits:6'
        ]);

        $user = User::where(
            'nik',
            $request->nik
        )->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'NIK atau OTP tidak valid'
            ], 400);
        }

        if ($user->approval !== 'approved' || $user->sts !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Akun belum siap untuk aktivasi OTP'
            ], 400);
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
            'sts' => 'aktif',
            'tglapproval' => now(),
        ]);
        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Approved',
            'description' => 'Akun disetujui admin',
            ...ActivityLogContext::fromRequest($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diaktifkan'
        ]);
    }
}
