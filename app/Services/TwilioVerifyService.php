<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class TwilioVerifyService
{
    private TwilioClient $client;
    private string $verifyServiceSid;

    public function __construct()
    {
        $this->client = new TwilioClient(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        
        $this->verifyServiceSid = config('services.twilio.verify_service_sid');
    }

    /**
     * Send OTP verification code to phone number
     */
    public function sendVerification(string $phoneNumber, string $channel = 'sms'): array
    {
        try {
            // Normalize phone number to E.164 format
            $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
            
            Log::info('Sending Twilio verification', [
                'phone' => $this->maskPhone($normalizedPhone),
                'channel' => $channel,
                'service_sid' => $this->verifyServiceSid
            ]);

            $verification = $this->client->verify->v2
                ->services($this->verifyServiceSid)
                ->verifications
                ->create($normalizedPhone, $channel);

            Log::info('Twilio verification sent successfully', [
                'phone' => $this->maskPhone($normalizedPhone),
                'status' => $verification->status,
                'sid' => $verification->sid
            ]);

            return [
                'success' => true,
                'status' => $verification->status,
                'sid' => $verification->sid,
                'message' => 'Verification code sent to ' . $this->maskPhone($normalizedPhone)
            ];

        } catch (TwilioException $e) {
            Log::error('Twilio verification send failed', [
                'phone' => $this->maskPhone($phoneNumber),
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'message' => 'Failed to send verification code. Please try again.'
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error sending verification', [
                'phone' => $this->maskPhone($phoneNumber),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'An unexpected error occurred. Please try again.'
            ];
        }
    }

    /**
     * Verify OTP code for phone number
     */
    public function verifyCode(string $phoneNumber, string $code): array
    {
        try {
            // Normalize phone number to E.164 format
            $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
            
            Log::info('Verifying Twilio code', [
                'phone' => $this->maskPhone($normalizedPhone),
                'service_sid' => $this->verifyServiceSid
            ]);

            $verificationCheck = $this->client->verify->v2
                ->services($this->verifyServiceSid)
                ->verificationChecks
                ->create([
                    'to' => $normalizedPhone,
                    'code' => $code
                ]);

            Log::info('Twilio verification check completed', [
                'phone' => $this->maskPhone($normalizedPhone),
                'status' => $verificationCheck->status,
                'sid' => $verificationCheck->sid
            ]);

            $isValid = $verificationCheck->status === 'approved';

            return [
                'success' => $isValid,
                'status' => $verificationCheck->status,
                'sid' => $verificationCheck->sid,
                'valid' => $isValid,
                'message' => $isValid 
                    ? 'Phone number verified successfully' 
                    : 'Invalid verification code. Please try again.'
            ];

        } catch (TwilioException $e) {
            Log::error('Twilio verification check failed', [
                'phone' => $this->maskPhone($phoneNumber),
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'success' => false,
                'valid' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'message' => 'Verification failed. Please check your code and try again.'
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error verifying code', [
                'phone' => $this->maskPhone($phoneNumber),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'valid' => false,
                'error' => $e->getMessage(),
                'message' => 'An unexpected error occurred. Please try again.'
            ];
        }
    }

    /**
     * Cancel pending verification for phone number
     */
    public function cancelVerification(string $phoneNumber): array
    {
        try {
            $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
            
            // Get pending verifications
            $verifications = $this->client->verify->v2
                ->services($this->verifyServiceSid)
                ->verifications
                ->read(['to' => $normalizedPhone], 20);

            $cancelledCount = 0;
            foreach ($verifications as $verification) {
                if ($verification->status === 'pending') {
                    $verification->update(['status' => 'canceled']);
                    $cancelledCount++;
                }
            }

            return [
                'success' => true,
                'cancelled_count' => $cancelledCount,
                'message' => "Cancelled {$cancelledCount} pending verification(s)"
            ];

        } catch (TwilioException $e) {
            Log::error('Failed to cancel Twilio verification', [
                'phone' => $this->maskPhone($phoneNumber),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to cancel verification'
            ];
        }
    }

    /**
     * Normalize phone number to E.164 format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // Handle Philippine numbers
        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            // Convert 09XXXXXXXXX to +639XXXXXXXXX
            return '+63' . substr($digits, 1);
        }

        // Handle numbers that already have country code but no +
        if (strlen($digits) > 10 && !str_starts_with($phone, '+')) {
            return '+' . $digits;
        }

        // If it already starts with +, return as is
        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        // Default: assume it needs + prefix
        return '+' . $digits;
    }

    /**
     * Mask phone number for logging: +639171234567 → +63917***4567
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 7) {
            return $phone;
        }
        
        return substr($phone, 0, -7) . '***' . substr($phone, -4);
    }

    /**
     * Check if phone number is Philippine number
     */
    private function isPhilippineNumber(string $phone): bool
    {
        return str_starts_with($phone, '+63');
    }
}