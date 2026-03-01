<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    /**
     * Maximum failed login attempts before lockout.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in seconds.
     */
    private const LOCKOUT_SECONDS = 300; // 5 minutes

    /**
     * Attempt to authenticate a user.
     */
    public function authenticate(string $email, string $password, string $ip): User
    {
        $key = $this->throttleKey($email, $ip);

        // Check if too many attempts
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            
            $this->logFailedAttempt($email, $ip);
            
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            
            $this->logFailedAttempt($email, $ip);
            
            // Generic error message to prevent user enumeration
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if account is active
        if (isset($user->is_active) && !$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        // Log successful authentication
        AuditLogger::logAuth('login', $user->id, true);

        return $user;
    }

    /**
     * Create authentication token for user.
     */
    public function createToken(User $user, string $deviceName, array $abilities = ['*']): string
    {
        return $user->createToken($deviceName, $abilities)->plainTextToken;
    }

    /**
     * Revoke all tokens for a user (useful on password change).
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
        
        AuditLogger::logAuth('tokens_revoked', $user->id, true);
    }

    /**
     * Revoke current token.
     */
    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
        
        AuditLogger::logAuth('logout', $user->id, true);
    }

    /**
     * Generate throttle key for rate limiting.
     */
    private function throttleKey(string $email, string $ip): string
    {
        return 'login:' . strtolower($email) . '|' . $ip;
    }

    /**
     * Log failed login attempt.
     */
    private function logFailedAttempt(string $email, string $ip): void
    {
        DB::table('failed_login_attempts')->insert([
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => request()->userAgent(),
            'attempted_at' => now(),
        ]);

        AuditLogger::logAuth('login_failed', null, false);
    }
}
