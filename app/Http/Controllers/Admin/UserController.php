<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Services\WhatsappService;
use App\Models\User;
use Carbon\Carbon;
use App\Models\ActivityLog;

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
    public function rejectUser($id){
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
            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Rejected',
                'description' => 'Akun ditolak admin'
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


    public function disableUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->update([
            'sts' => 'disabled',
            'tgldisabled' => Carbon::now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dinonaktifkan'
        ]);
    }

    public function enableUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->update([
            'sts' => 'aktif',
            'tgldisabled' => null
        ]);
        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Account Enabled',
            'description' => 'Akun diaktifkan kembali oleh admin'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diaktifkan'
        ]);
    }

    public function dashboard()
    {
        $totalUser = User::where('role', 'user')->count();

        $aktif = User::where('role', 'user')
            ->where('sts', 'aktif')
            ->count();

        $pending = User::where('role', 'user')
            ->where('approval', 'pending')
            ->count();

        $rejected = User::where('role', 'user')
            ->where('approval', 'rejected')
            ->count();

        $recentUsers = User::where('role', 'user')
            ->orderByDesc('tgldaftar')
            ->take(5)
            ->get([
                'id',
                'nik',
                'nama',
                'instansi',
                'jabatan',
                'telp',
                'sts',
                'approval',
                'tgldaftar'
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_user' => $totalUser,
                    'aktif' => $aktif,
                    'pending' => $pending,
                    'rejected' => $rejected,
                ],
                'recent_users' => $recentUsers,
            ]
        ]);
    }
}