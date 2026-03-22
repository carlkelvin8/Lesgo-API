<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

class SmsService
{
    /**
     * Send SMS — Twilio primary, Semaphore fallback for PH numbers.
     */
    public function send(string $to, string $message): bool
    {
        // Normalize PH numbers to E.164
        $normalized = $this->normalizePhone($to);

        // Use Semaphore for PH numbers if configured
        if ($this->isPhilippineNumber($normalized) && config('services.semaphore.api_key')) {
            return $this->sendViaSemaphore($normalized, $message);
        }

        return $this->sendViaTwilio($normalized, $message);
    }

    private function sendViaTwilio(string $to, string $message): bool
    {
        try {
            $client = new TwilioClient(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $client->messages->create($to, [
                'from' => config('services.twilio.from'),
                'body' => $message,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Twilio SMS failed', ['to' => $to, 'error' => $e->getMessage()]);

            // Fallback to Semaphore if PH number
            if ($this->isPhilippineNumber($to) && config('services.semaphore.api_key')) {
                return $this->sendViaSemaphore($to, $message);
            }

            return false;
        }
    }

    private function sendViaSemaphore(string $to, string $message): bool
    {
        try {
            $response = \Illuminate\Support\Facades\Http::post('https://api.semaphore.co/api/v4/messages', [
                'apikey'      => config('services.semaphore.api_key'),
                'number'      => $to,
                'message'     => $message,
                'sendername'  => config('services.semaphore.sender_name', 'LESGO'),
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Semaphore SMS failed', [
                'to'     => $to,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::warning('Semaphore SMS exception', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // PH local: 09XXXXXXXXX → +639XXXXXXXXX
        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return '+63' . substr($digits, 1);
        }

        // Already has country code without +
        if (!str_starts_with($phone, '+')) {
            return '+' . $digits;
        }

        return $phone;
    }

    private function isPhilippineNumber(string $phone): bool
    {
        return str_starts_with($phone, '+63');
    }
}
