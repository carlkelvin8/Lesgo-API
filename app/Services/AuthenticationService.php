<?php

namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Auth\AuthenticationException;
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
     * Default refresh token lifetime in days.
     */
    private const REFRESH_TOKEN_DAYS = 30;

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

            throw new AuthenticationException(
                "Too many login attempts. Please try again in {$seconds} seconds."
            );
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            $this->logFailedAttempt($email, $ip);

            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        // Check if account is active
        if ($user->is_active === false) {
            throw new AuthenticationException('Your account has been deactivated. Please contact support.');
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
     * Create access + refresh token pair for API clients.
     *
     * @return array{token: string, refresh_token: string, expires_in: int}
     */
    public function issueTokenPair(User $user, string $deviceName): array
    {
        return [
            'token'         => $this->createToken($user, $deviceName),
            'refresh_token' => $this->createRefreshToken($user, $deviceName),
            'expires_in'    => 3600,
        ];
    }

    /**
     * Create a long-lived refresh token for the user.
     */
    public function createRefreshToken(User $user, string $deviceName): string
    {
        $plainToken = bin2hex(random_bytes(32));

        RefreshToken::create([
            'user_id'      => $user->id,
            'token_hash'   => hash('sha256', $plainToken),
            'device_name'  => $deviceName,
            'expires_at'   => now()->addDays(self::REFRESH_TOKEN_DAYS),
        ]);

        return $plainToken;
    }

    /**
     * Issue a new access + refresh token pair, revoking the consumed refresh token.
     *
     * @return array{user: User, accessToken: string, refreshToken: string}
     */
    public function rotateRefreshToken(string $plainToken, string $deviceName = 'api-token'): array
    {
        $record = RefreshToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            throw new AuthenticationException('Invalid or expired refresh token.');
        }

        $user = User::find($record->user_id);

        if (!$user || $user->is_active === false) {
            $record->update(['revoked_at' => now()]);
            throw new AuthenticationException('Account is deactivated.');
        }

        $record->update(['revoked_at' => now()]);

        return [
            'user'          => $user,
            'accessToken'   => $this->createToken($user, $deviceName),
            'refreshToken'  => $this->createRefreshToken($user, $deviceName),
        ];
    }

    /**
     * Revoke all refresh tokens for a user.
     */
    public function revokeAllRefreshTokens(User $user): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Revoke all tokens for a user (useful on password change).
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
        $this->revokeAllRefreshTokens($user);

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
        try {
            DB::table('failed_login_attempts')->insert([
                'email'        => $email,
                'ip_address'   => $ip,
                'user_agent'   => request()->userAgent(),
                'attempted_at' => now(),
            ]);
        } catch (\Throwable) {
            // Non-critical — don't let logging crash the auth flow
        }

        AuditLogger::logAuth('login_failed', null, false);
    }
}
