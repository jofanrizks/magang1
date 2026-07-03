<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('nik', $request->nik)->first();

        $credentials = $request->only('nik', 'password');

        if (!$token = auth()->attempt($credentials)) {

            if ($user) {

                ActivityLog::create([
                    'user_id' => $user->id,
                    'activity' => 'Failed Login',
                    'description' => 'Percobaan login gagal (password salah)',
                    'ip_address' => $request->ip(),
                ]);

                // Ambil 3 aktivitas terakhir
                $lastActivities = ActivityLog::where('user_id', $user->id)
                    ->latest()
                    ->take(3)
                    ->pluck('activity');

                // Jika 3 aktivitas terakhir semuanya Failed Login
                if (
                    $lastActivities->count() === 3 &&
                    $lastActivities->every(fn($activity) => $activity === 'Failed Login')
                ) {

                    $user->update([
                        'sts' => 'disabled',
                        'tgldisabled' => now(),
                    ]);

                    ActivityLog::create([
                        'user_id' => $user->id,
                        'activity' => 'Account Disabled',
                        'description' => 'Akun dinonaktifkan otomatis karena 3 kali gagal login berturut-turut',
                        'ip_address' => $request->ip(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Akun dinonaktifkan karena 3 kali gagal login berturut-turut.'
                    ], 403);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'NIK atau password salah'
            ], 401);
        }

        $user = auth()->user();

        // BELUM APPROVAL
        if ($user->approval != 'approved') {

            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Failed Login',
                'description' => 'Login ditolak karena akun belum disetujui admin',
                'ip_address' => $request->ip(),
            ]);

            auth()->logout();

            return response()->json([
                'success' => false,
                'message' => 'Menunggu approval admin'
            ], 403);
        }

        // AKUN DISABLED
        if ($user->sts == 'disabled') {

            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Failed Login',
                'description' => 'Login ditolak karena akun dinonaktifkan',
                'ip_address' => $request->ip(),
            ]);

            auth()->logout();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang dinonaktifkan. Silakan aktifkan kembali akun Anda.'
            ], 403);
        }

        // AKUN TIDAK AKTIF
        if ($user->sts != 'aktif') {

            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Failed Login',
                'description' => 'Login ditolak karena akun tidak aktif',
                'ip_address' => $request->ip(),
            ]);

            auth()->logout();

            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif'
            ], 403);
        }

        // LOGIN BERHASIL
        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Login',
            'description' => 'User berhasil login ke sistem',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user
        ]);
    }
    public function logout(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Logout',
                'description' => 'User keluar dari sistem',
                'ip_address' => $request->ip(),
            ]);
        }

        auth()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }



    public function me()
    {
        return response()->json(auth()->user());
    }
}