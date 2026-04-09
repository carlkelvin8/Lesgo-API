<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Jenssegers\Agent\Agent;

class SessionManagementService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    /**
     * Create a new session record when user logs in.
     */
    public function createSession(User $user, PersonalAccessToken $token, Request $request): UserSession
    {
        // Parse user agent
        $this->agent->setUserAgent($request->userAgent());

        // Get location data (you can integrate with IP geolocation service)
        $locationData = $this->getLocationData($request->ip());

        return UserSession::create([
            'user_id' => $user->id,
            'token_id' => $token->id,
            'device_name' => $token->name,
            'device_type' => $this->getDeviceType(),
            'device_id' => $this->generateDeviceId($request),
            'platform' => $this->agent->platform(),
            'browser' => $this->agent->browser(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'location_data' => $locationData,
            'last_activity' => now(),
            'expires_at' => $token->expires_at,
            'is_active' => true,
            'is_trusted_device' => $this->isTrustedDevice($user, $request),
        ]);
    }

    /**
     * Update session activity.
     */
    public function updateSessionActivity(PersonalAccessToken $token): void
    {
        UserSession::where('token_id', $token->id)
            ->where('is_active', true)
            ->update(['last_activity' => now()]);
    }

    /**
     * Terminate a specific session.
     */
    public function terminateSession(UserSession $session): bool
    {
        DB::transaction(function () use ($session) {
            // Revoke the Sanctum token
            PersonalAccessToken::find($session->token_id)?->delete();
            
            // Mark session as inactive
            $session->deactivate();
        });

        return true;
    }

    /**
     * Terminate all sessions for a user except current.
     */
    public function terminateOtherSessions(User $user, ?string $currentTokenId = null): int
    {
        $sessionsQuery = $user->sessions()->active();
        
        if ($currentTokenId) {
            $sessionsQuery->where('token_id', '!=', $currentTokenId);
        }

        $sessions = $sessionsQuery->get();
        $terminatedCount = 0;

        foreach ($sessions as $session) {
            if ($this->terminateSession($session)) {
                $terminatedCount++;
            }
        }

        return $terminatedCount;
    }

    /**
     * Enforce concurrent session limits.
     */
    public function enforceConcurrentSessionLimit(User $user, int $maxSessions = 3): void
    {
        $activeSessions = $user->sessions()->active()->orderBy('last_activity', 'desc')->get();

        if ($activeSessions->count() > $maxSessions) {
            // Terminate oldest sessions
            $sessionsToTerminate = $activeSessions->skip($maxSessions);
            
            foreach ($sessionsToTerminate as $session) {
                $this->terminateSession($session);
            }
        }
    }

    /**
     * Get active sessions for a user.
     */
    public function getActiveSessions(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->sessions()
            ->active()
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    /**
     * Clean up expired sessions.
     */
    public function cleanupExpiredSessions(): int
    {
        $expiredSessions = UserSession::expired()->get();
        $cleanedCount = 0;

        foreach ($expiredSessions as $session) {
            if ($this->terminateSession($session)) {
                $cleanedCount++;
            }
        }

        return $cleanedCount;
    }

    /**
     * Get session statistics for a user.
     */
    public function getSessionStats(User $user): array
    {
        $sessions = $user->sessions();

        return [
            'total_sessions' => $sessions->count(),
            'active_sessions' => $sessions->active()->count(),
            'mobile_sessions' => $sessions->active()->byDeviceType('mobile')->count(),
            'desktop_sessions' => $sessions->active()->byDeviceType('desktop')->count(),
            'trusted_devices' => $sessions->where('is_trusted_device', true)->count(),
            'last_activity' => $sessions->active()->max('last_activity'),
        ];
    }

    /**
     * Check if device should be trusted.
     */
    protected function isTrustedDevice(User $user, Request $request): bool
    {
        $deviceId = $this->generateDeviceId($request);
        
        // Check if this device has been used before and marked as trusted
        return $user->sessions()
            ->where('device_id', $deviceId)
            ->where('is_trusted_device', true)
            ->exists();
    }

    /**
     * Generate a unique device identifier.
     */
    protected function generateDeviceId(Request $request): string
    {
        // Create a hash based on user agent and other device characteristics
        $deviceString = $request->userAgent() . $request->header('Accept-Language', '');
        return hash('sha256', $deviceString);
    }

    /**
     * Determine device type.
     */
    protected function getDeviceType(): string
    {
        if ($this->agent->isMobile()) {
            return 'mobile';
        } elseif ($this->agent->isTablet()) {
            return 'tablet';
        } elseif ($this->agent->isDesktop()) {
            return 'desktop';
        }

        return 'unknown';
    }

    /**
     * Get location data from IP address.
     * You can integrate with services like MaxMind, IPinfo, etc.
     */
    protected function getLocationData(string $ip): ?array
    {
        // For now, return null. You can integrate with IP geolocation service
        // Example with IPinfo: https://ipinfo.io/{$ip}/json
        
        try {
            // Placeholder for IP geolocation
            if ($ip === '127.0.0.1' || $ip === '::1') {
                return [
                    'country' => 'Local',
                    'city' => 'Localhost',
                    'region' => 'Development',
                ];
            }

            // You can implement actual geolocation here
            return [
                'country' => 'Unknown',
                'city' => 'Unknown',
                'region' => 'Unknown',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Mark device as trusted.
     */
    public function markDeviceAsTrusted(UserSession $session): bool
    {
        return $session->update(['is_trusted_device' => true]);
    }

    /**
     * Get concurrent session limit for user role.
     */
    public function getConcurrentSessionLimit(User $user): int
    {
        return match ($user->role) {
            'customer' => 3,      // Customers can have 3 concurrent sessions
            'driver' => 2,        // Drivers can have 2 concurrent sessions  
            'partner_admin' => 5, // Partner admins can have 5 concurrent sessions
            default => 2,
        };
    }
}