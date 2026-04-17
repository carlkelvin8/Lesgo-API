<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    /**
     * POST /api/v1/auth/otp/send
     * Generate and send a 6-digit OTP to the given phone number.
     * Stores the OTP in cache for 10 minutes.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:7|max:20',
        ]);

        $phone = $validated['phone_number'];

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Store in cache for 10 minutes (keyed by phone number)
        $cacheKey = 'otp:' . preg_replace('/[^0-9]/', '', $phone);
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        // Attempt to send via SMS service
        $smsSent = false;
        try {
            $smsService = app(\App\Services\SmsService::class);
            $smsService->send(
                $phone,
                "Your LeSgo verification code is: {$otp}. Valid for 10 minutes. Do not share this code."
            );
            $smsSent = true;
        } catch (\Exception $e) {
            Log::warning('OTP SMS send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            // Continue — OTP is still stored in cache
        }

        return response()->json([
            'success' => true,
            'message' => $smsSent
                ? 'OTP sent to ' . $this->maskPhone($phone)
                : 'OTP generated. SMS delivery may be delayed.',
            // Only expose OTP in non-production for testing
            'otp' => app()->isProduction() ? null : $otp,
        ]);
    }

    /**
     * POST /api/v1/auth/otp/verify
     * Verify the OTP for a given phone number.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:7|max:20',
            'otp'          => 'required|string|size:6',
        ]);

        $phone    = $validated['phone_number'];
        $inputOtp = $validated['otp'];

        $cacheKey   = 'otp:' . preg_replace('/[^0-9]/', '', $phone);
        $storedOtp  = Cache::get($cacheKey);

        if (!$storedOtp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired or was not sent. Please request a new one.',
            ], 422);
        }

        if ($storedOtp !== $inputOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP code. Please try again.',
            ], 422);
        }

        // OTP is valid — remove from cache
        Cache::forget($cacheKey);

        return response()->json([
            'success'  => true,
            'message'  => 'Phone number verified successfully.',
            'verified' => true,
        ]);
    }

    /**
     * Mask phone number for display: +639171234567 → +63917***4567
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 7) return $phone;
        return substr($phone, 0, -7) . '***' . substr($phone, -4);
    }
}
