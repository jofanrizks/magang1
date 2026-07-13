<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'nik' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('nik', $request->nik)->first();

        if ($user && $user->sts == 'disabled') {
            $this->logActivity(
                $user,
                'Login Failed',
                'Login ditolak karena akun dinonaktifkan',
                $request->ip()
            );

            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_DISABLED',
                'message' => 'Akun dinonaktifkan. Silakan aktifkan kembali menggunakan OTP.'
            ], 403);
        }

        $credentials = $request->only('nik', 'password');

        if (!$token = auth()->attempt($credentials)) {

            if ($user) {
                $attempts = $user->login_attempt + 1;

                $user->update([
                    'login_attempt' => $attempts,
                ]);

                $this->logActivity(
                    $user,
                    'Login Failed',
                    'Percobaan login gagal (password salah)',
                    $request->ip()
                );

                if ($attempts >= 3) {
                    $user->update([
                        'sts' => 'disabled',
                        'tgldisabled' => now(),
                    ]);

                    $this->logActivity(
                        $user,
                        'Account Disabled',
                        'Akun dinonaktifkan karena terlalu banyak percobaan login gagal',
                        $request->ip()
                    );

                    return response()->json([
                        'success' => false,
                        'code' => 'ACCOUNT_DISABLED',
                        'message' => 'Akun dinonaktifkan. Silakan aktifkan kembali menggunakan OTP.'
                    ], 403);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'NIK atau password salah'
            ], 401);
        }

        $user = auth()->user();

        if ($user->approval != 'approved') {

            $this->logActivity(
                $user,
                'Failed Login',
                'Login ditolak karena akun belum disetujui admin',
                $request->ip()
            );

            auth()->logout();

            return response()->json([
                'success' => false,
                'message' => 'Menunggu approval admin'
            ], 403);
        }

        if ($user->sts == 'disabled') {

            $this->logActivity(
                $user,
                'Login Failed',
                'Login ditolak karena akun dinonaktifkan',
                $request->ip()
            );

            auth()->logout();

            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_DISABLED',
                'message' => 'Akun dinonaktifkan. Silakan aktifkan kembali menggunakan OTP.'
            ], 403);
        }

        if ($user->sts != 'aktif') {

            $this->logActivity(
                $user,
                'Failed Login',
                'Login ditolak karena akun tidak aktif',
                $request->ip()
            );

            auth()->logout();

            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif'
            ], 403);
        }

        $user->update([
            'login_attempt' => 0,
        ]);

        $this->logActivity(
            $user,
            'Login',
            'User berhasil login ke sistem',
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token,
                'user' => $user->load('group'),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            $this->logActivity(
                $user,
                'Logout',
                'User keluar dari sistem',
                $request->ip()
            );
        }

        auth()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }



    public function me()
    {
        return response()->json([
            'success' => true,
            'data' => auth()->user()->load('group'),
        ]);
    }

    private function logActivity(User $user, string $activity, string $description, ?string $ipAddress): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => $activity,
            'description' => $description,
            'ip_address' => $ipAddress,
        ]);
    }
}
