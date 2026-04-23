<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwilioVerifyService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    private TwilioVerifyService $twilioVerifyService;

    public function __construct(TwilioVerifyService $twilioVerifyService)
    {
        $this->twilioVerifyService = $twilioVerifyService;
    }

    /**
     * POST /api/v1/auth/otp/send
     * Send OTP verification code using Twilio Verify API
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:7|max:20',
            'channel' => 'sometimes|string|in:sms,call', // Optional: sms or call
        ]);

        $phone = $validated['phone_number'];
        $channel = $validated['channel'] ?? 'sms';

        Log::info('OTP send request', [
            'phone' => $this->maskPhone($phone),
            'channel' => $channel,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Use Twilio Verify service
        $result = $this->twilioVerifyService->sendVerification($phone, $channel);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'status' => $result['status'],
                'channel' => $channel,
                // Only expose verification SID in non-production for debugging
                'verification_sid' => app()->isProduction() ? null : $result['sid'],
            ]);
        }

        // If Twilio Verify fails, fall back to cache-based OTP for development
        if (!app()->isProduction()) {
            return $this->sendFallbackOtp($phone);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'error' => $result['error'] ?? 'Failed to send verification code',
        ], 422);
    }

    /**
     * POST /api/v1/auth/otp/verify
     * Verify OTP code using Twilio Verify API
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:7|max:20',
            'otp' => 'required|string|min:4|max:8',
        ]);

        $phone = $validated['phone_number'];
        $code = $validated['otp'];

        Log::info('OTP verify request', [
            'phone' => $this->maskPhone($phone),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Use Twilio Verify service
        $result = $this->twilioVerifyService->verifyCode($phone, $code);

        if ($result['success'] && $result['valid']) {
            // Mark phone as verified in session/cache for registration flow
            $verificationKey = 'phone_verified:' . preg_replace('/[^0-9]/', '', $phone);
            Cache::put($verificationKey, true, now()->addHours(1));

            Log::info('OTP verification successful', [
                'phone' => $this->maskPhone($phone),
                'verification_sid' => $result['sid'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'verified' => true,
                'message' => $result['message'],
                'status' => $result['status'],
            ]);
        }

        // If Twilio Verify fails, fall back to cache-based OTP for development
        if (!app()->isProduction()) {
            return $this->verifyFallbackOtp($phone, $code);
        }

        Log::warning('OTP verification failed', [
            'phone' => $this->maskPhone($phone),
            'status' => $result['status'] ?? 'unknown',
            'error' => $result['error'] ?? 'Invalid code'
        ]);

        return response()->json([
            'success' => false,
            'verified' => false,
            'message' => $result['message'],
            'error' => $result['error'] ?? 'Invalid verification code',
        ], 422);
    }

    /**
     * POST /api/v1/auth/otp/cancel
     * Cancel pending verification (optional endpoint)
     */
    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:7|max:20',
        ]);

        $phone = $validated['phone_number'];
        $result = $this->twilioVerifyService->cancelVerification($phone);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Fallback OTP system for development/testing
     */
    private function sendFallbackOtp(string $phone): JsonResponse
    {
        try {
            // Generate 6-digit OTP
            $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // Store in cache for 10 minutes
            $cacheKey = 'fallback_otp:' . preg_replace('/[^0-9]/', '', $phone);
            Cache::put($cacheKey, $otp, now()->addMinutes(10));

            Log::info('Fallback OTP generated', [
                'phone' => $this->maskPhone($phone),
                'otp' => $otp // Only log in development
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent (fallback mode)',
                'status' => 'pending',
                'channel' => 'sms',
                'otp' => $otp, // Expose for testing
                'fallback' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Fallback OTP generation failed', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate verification code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify fallback OTP for development/testing
     */
    private function verifyFallbackOtp(string $phone, string $code): JsonResponse
    {
        $cacheKey = 'fallback_otp:' . preg_replace('/[^0-9]/', '', $phone);
        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp) {
            return response()->json([
                'success' => false,
                'verified' => false,
                'message' => 'Verification code has expired. Please request a new one.',
                'fallback' => true,
            ], 422);
        }

        if ($storedOtp !== $code) {
            return response()->json([
                'success' => false,
                'verified' => false,
                'message' => 'Invalid verification code. Please try again.',
                'fallback' => true,
            ], 422);
        }

        // Valid OTP - remove from cache and mark as verified
        Cache::forget($cacheKey);
        $verificationKey = 'phone_verified:' . preg_replace('/[^0-9]/', '', $phone);
        Cache::put($verificationKey, true, now()->addHours(1));

        Log::info('Fallback OTP verification successful', [
            'phone' => $this->maskPhone($phone)
        ]);

        return response()->json([
            'success' => true,
            'verified' => true,
            'message' => 'Phone number verified successfully (fallback mode)',
            'status' => 'approved',
            'fallback' => true,
        ]);
    }

    /**
     * Check if phone number is verified (helper method for registration)
     */
    public function checkVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|min:7|max:20',
        ]);

        $phone = $validated['phone_number'];
        $verificationKey = 'phone_verified:' . preg_replace('/[^0-9]/', '', $phone);
        $isVerified = Cache::has($verificationKey);

        return response()->json([
            'phone_number' => $phone,
            'verified' => $isVerified,
            'message' => $isVerified 
                ? 'Phone number is verified' 
                : 'Phone number is not verified',
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
