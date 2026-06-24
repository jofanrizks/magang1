<?php

namespace App\Services;

use App\Models\VerificationCode;
use Carbon\Carbon;

class OtpService
{
    public function generate($userId, $type = 'activation')
    {
        VerificationCode::where('user_id', $userId)
            ->where('type', $type)
            ->where('is_used', false)
            ->delete();

        $otp = rand(1000, 9999);

        return VerificationCode::create([
            'user_id' => $userId,
            'code' => $otp,
            'type' => $type,
            'expired_at' => Carbon::now()->addMinutes(5),
        ]);
    }

    public function verify($userId, $code, $type)
    {

        $otp = VerificationCode::where('user_id', $userId)
            ->where('code', trim($code))
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expired_at', '>', now())
            ->first();

        if (!$otp) {
            return false;
        }

        $otp->update([
            'is_used' => true
        ]);

        return true;
    }
}