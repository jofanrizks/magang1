<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\OtpService;
use App\Services\WhatsappService;
use App\Models\ActivityLog;
use App\Models\User;

class AccountController extends Controller
{
    public function sendReactivateOtp(
        Request $request,
        OtpService $otpService,
        WhatsappService $whatsappService
    ) {
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

        if ($user->role != 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Reaktivasi hanya untuk akun user'
            ], 403);
        }

        if ($user->sts != 'disabled') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak dalam status disabled'
            ], 400);
        }

        $otp = $otpService->generate(
            $user->id,
            'reactivate_account'
        );

        $sent = $whatsappService->send(
            $user->telp,
            "Kode OTP untuk reaktivasi akun Anda adalah {$otp->plain_code}"
        );

        if (!$sent) {
            $otp->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP gagal dikirim'
            ], 502);
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Reactivate OTP Sent',
            'description' => 'OTP reaktivasi akun dikirim',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil dikirim ke WhatsApp'
        ]);
    }

    public function reactivateAccount(
        Request $request,
        OtpService $otpService
    ) {
        $request->validate([
            'nik' => 'required',
            'code' => 'required|digits:6'
        ]);

        $user = User::where('nik', $request->nik)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        if ($user->role != 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Reaktivasi hanya untuk akun user'
            ], 403);
        }

        if ($user->sts != 'disabled') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak dalam status disabled'
            ], 400);
        }

        $valid = $otpService->verify(
            $user->id,
            $request->code,
            'reactivate_account'
        );

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'OTP salah atau sudah expired'
            ], 400);
        }

        $user->update([
            'sts' => 'aktif',
            'login_attempt' => 0,
            'tgldisabled' => null,
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Reactivate Account',
            'description' => 'Akun berhasil diaktifkan kembali menggunakan OTP',
            'ip_address' => $request->ip(),
        ]);

        $activityLogs = ActivityLog::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get([
                'id',
                'user_id',
                'activity',
                'description',
                'ip_address',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diaktifkan kembali',
            'data' => [
                'user' => $user->fresh()->makeHidden([
                    'password',
                    'remember_token',
                ]),
                'activity_logs' => $activityLogs,
            ],
        ]);
    }

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

        $sent = $whatsappService->send(
            $user->telp,
            "Kode OTP untuk menonaktifkan akun Anda adalah {$otp->plain_code}"
        );

        if (!$sent) {
            $otp->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP gagal dikirim'
            ], 502);
        }

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
            'otp' => 'required|digits:6'
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

    public function changeRequiredPassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama salah'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'activity' => 'Required Password Changed',
            'description' => 'Pengguna mengganti password wajib',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui',
            'data' => $user->fresh()->load('groups:id,name'),
        ]);
    }
}
