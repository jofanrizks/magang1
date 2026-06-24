<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Services\WhatsappService;
use App\Models\User;

class UserController extends Controller
{
    public function pendingUsers()
    {
        $users = User::where('approval', 'pending')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
    public function sendOtp(
        $id,
        OtpService $otpService,
        WhatsappService $whatsappService
    )
    {
        $user = User::findOrFail($id);

        $otp = $otpService->generate(
            $user->id,
            'activation'
        );

        $whatsappService->send(
            $user->telp,
            "Kode aktivasi Anda: {$otp->code}"
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil dikirim',
            'otp' => $otp->code 
        ]);
    }
    public function rejectUser($id)
    {
        $user = User::findOrFail($id);

        if ($user->approval !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'User sudah diproses sebelumnya'
            ], 400);
        }

        $user->update([
            'approval' => 'rejected',
            'sts' => 'disabled',
            'tgldisabled' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditolak'
        ]);
    }

    public function getAllUsers()
    {
        $users = User::all();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
    public function getApprovedUsers()
    {
        $users = User::where('approval', 'approved')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }


}