<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Send OTP to email for password reset
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Rate limiting: 3 attempts per 5 minutes per email
        $key = 'forgot-password:' . $email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Please try again in " . ceil($seconds / 60) . " minutes.",
            ], 429);
        }

        RateLimiter::hit($key, 300); // 5 minutes

        // Check if user exists
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            // Don't reveal if email exists or not (security best practice)
            return response()->json([
                'success' => true,
                'message' => 'If this email is registered, you will receive an OTP code.',
            ]);
        }

        // Invalidate previous OTPs for this email
        PasswordReset::where('email', $email)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in database
        PasswordReset::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10), // 10 minutes expiry
            'ip_address' => $request->ip(),
        ]);

        // Send email
        try {
            Mail::to($email)->send(new PasswordResetOtpMail(
                otp: $otp,
                userName: $user->name,
                expiryMinutes: 10
            ));

            \Log::info("Password reset OTP sent to: {$email}");
        } catch (\Exception $e) {
            \Log::error("Failed to send password reset OTP: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email. Please check your inbox.',
            'expires_in_minutes' => 10,
        ]);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $email = $request->email;
        $otp = $request->otp;

        // Rate limiting: 10 attempts per minute
        $key = 'verify-otp:' . $email;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many verification attempts. Please try again later.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        // Find valid OTP
        $passwordReset = PasswordReset::where('email', $email)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        // Generate reset token (valid for 15 minutes)
        $resetToken = Str::random(64);
        
        // Store reset token in the password_reset record
        $passwordReset->update([
            'reset_token' => $resetToken,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * Reset password using verified OTP
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $request->email;
        $otp = $request->otp;

        // Find valid OTP
        $passwordReset = PasswordReset::where('email', $email)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Mark OTP as used
        $passwordReset->markAsUsed();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        \Log::info("Password reset successful for user: {$email}");

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request): JsonResponse
    {
        // Same as sendOtp but with different rate limit
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Rate limiting: 2 resends per 10 minutes
        $key = 'resend-otp:' . $email;
        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many resend attempts. Please try again in " . ceil($seconds / 60) . " minutes.",
            ], 429);
        }

        RateLimiter::hit($key, 600); // 10 minutes

        return $this->sendOtp($request);
    }
}
