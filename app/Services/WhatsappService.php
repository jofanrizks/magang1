<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappService
{
    public function send($phone, $message)
    {
        if (substr($phone, 0, 1) == '0') {
            $phone = '62' . substr($phone, 1);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => config('services.fonnte.token')
                ])->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                    'countryCode' => '62'
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('Failed to send WhatsApp message.', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
