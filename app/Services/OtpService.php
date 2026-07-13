<?php

namespace App\Services;

use App\Models\VerificationCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function generate($userId, $type = 'activation')
    {
        VerificationCode::where('user_id', $userId)
            ->where('type', $type)
            ->where('is_used', false)
            ->delete();

        $otp = (string) random_int(100000, 999999);

        $verificationCode = VerificationCode::create([
            'user_id' => $userId,
            'code' => Hash::make($otp),
            'type' => $type,
            'expired_at' => Carbon::now()->addMinutes(5),
        ]);

        $verificationCode->plain_code = $otp;

        return $verificationCode;
    }

    public function verify($userId, $code, $type)
    {

        $otp = VerificationCode::where('user_id', $userId)
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expired_at', '>', now())
            ->first();

        if (!$otp || !Hash::check(trim($code), $otp->code)) {
            return false;
        }

        $otp->update([
            'is_used' => true
        ]);

        return true;
    }
}
