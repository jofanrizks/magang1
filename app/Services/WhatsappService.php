<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappService
{
    public function send($phone, $message)
    {
        if (substr($phone, 0, 1) == '0') {
            $phone = '62' . substr($phone, 1);
        }

        return Http::withHeaders([
            'Authorization' => env('FONNTE_TOKEN')
        ])->post('https://api.fonnte.com/send', [
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62'
        ]);
    }
}