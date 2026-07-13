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
        $users = User::where('approval', 'pending')
            ->select($this->userListFields())
            ->paginate(25);

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

        $sent = $whatsappService->send(
            $user->telp,
            "Kode aktivasi Anda: {$otp->plain_code}"
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
            'message' => 'OTP berhasil dikirim'
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
            $users = User::select($this->userListFields())
                ->paginate(25);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        }
    public function getApprovedUsers()
        {
            $users = User::where('approval', 'approved')
                ->select($this->userListFields())
                ->paginate(25);

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
        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Account Disabled',
            'description' => 'Akun dinonaktifkan oleh admin'
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
    public function logUser($id)
    {
        $user = User::with([
            'activityLogs' => fn($q) => $q->latest()
        ])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    private function userListFields(): array
    {
        return [
            'id',
            'role',
            'group_id',
            'nik',
            'nama',
            'instansi',
            'jabatan',
            'telp',
            'sts',
            'approval',
            'login_attempt',
            'tgldaftar',
            'tglapproval',
            'tgldisabled',
        ];
    }
}
