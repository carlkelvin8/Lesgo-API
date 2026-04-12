<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Device Fingerprinting Service
 * 
 * Creates unique device fingerprints for fraud detection and device tracking.
 * Combines multiple device attributes to create a robust fingerprint.
 */
class DeviceFingerprintService
{
    /**
     * Cache duration for device fingerprints (30 days)
     */
    private const CACHE_DAYS = 30;

    /**
     * Create a device fingerprint from request
     */
    public function createFingerprint(Request $request): string
    {
        $components = $this->extractDeviceComponents($request);
        
        // Generate fingerprint hash from components
        $fingerprintString = implode('|', array_values($components));
        $fingerprint = hash('sha256', $fingerprintString);

        // Store device info if not already stored
        if (!$this->hasDeviceData($fingerprint)) {
            $this->storeDeviceData($fingerprint, $components, $request);
        }

        return $fingerprint;
    }

    /**
     * Extract device components from request
     */
    private function extractDeviceComponents(Request $request): array
    {
        return [
            'user_agent' => $request->userAgent() ?? 'unknown',
            'accept_language' => $request->header('Accept-Language') ?? '',
            'accept_encoding' => $request->header('Accept-Encoding') ?? '',
            'accept_charset' => $request->header('Accept-Charset') ?? '',
            'screen_resolution' => $request->header('X-Screen-Resolution') ?? '',
            'timezone' => $request->header('X-Timezone') ?? '',
            'platform' => $request->header('X-Platform') ?? '',
            'browser' => $request->header('X-Browser') ?? '',
            'browser_version' => $request->header('X-Browser-Version') ?? '',
            'os' => $request->header('X-OS') ?? '',
            'os_version' => $request->header('X-OS-Version') ?? '',
            'device_model' => $request->header('X-Device-Model') ?? '',
            'cpu_cores' => $request->header('X-CPU-Cores') ?? '',
            'memory' => $request->header('X-Memory') ?? '',
            'canvas_fingerprint' => $request->header('X-Canvas-Fingerprint') ?? '',
            'webgl_fingerprint' => $request->header('X-WebGL-Fingerprint') ?? '',
            'ip_address' => $request->ip(),
        ];
    }

    /**
     * Get or create fingerprint for user
     */
    public function getOrCreateUserFingerprint(Request $request, int $userId): array
    {
        $fingerprint = $this->createFingerprint($request);
        $cacheKey = "device_fingerprint:user:{$userId}:{$fingerprint}";

        $deviceData = Cache::remember($cacheKey, now()->days(self::CACHE_DAYS), function () use ($fingerprint, $userId, $request) {
            // Check if this device is already registered for this user
            $existingDevice = \App\Models\UserSession::where('user_id', $userId)
                ->where('device_fingerprint', $fingerprint)
                ->first();

            if ($existingDevice) {
                return $existingDevice->device_data ?? [];
            }

            // New device for this user - flag for review if user has multiple devices
            $deviceCount = \App\Models\UserSession::where('user_id', $userId)
                ->distinct('device_fingerprint')
                ->count('device_fingerprint');

            $isSuspicious = $deviceCount >= 5; // Flag if user has 5+ devices

            if ($isSuspicious) {
                $this->logSuspiciousDevice($userId, $fingerprint, $request);
            }

            return [
                'fingerprint' => $fingerprint,
                'user_id' => $userId,
                'first_seen' => now()->toISOString(),
                'is_trusted' => !$isSuspicious,
                'is_suspicious' => $isSuspicious,
            ];
        });

        return $deviceData;
    }

    /**
     * Check if device is trusted
     */
    public function isDeviceTrusted(Request $request, int $userId): bool
    {
        $fingerprint = $this->createFingerprint($request);
        $cacheKey = "device_fingerprint:user:{$userId}:{$fingerprint}";

        $deviceData = Cache::get($cacheKey);

        return $deviceData && ($deviceData['is_trusted'] ?? false);
    }

    /**
     * Mark device as trusted (e.g., after 2FA verification)
     */
    public function trustDevice(Request $request, int $userId): bool
    {
        $fingerprint = $this->createFingerprint($request);
        $cacheKey = "device_fingerprint:user:{$userId}:{$fingerprint}";

        $deviceData = Cache::get($cacheKey);

        if ($deviceData) {
            $deviceData['is_trusted'] = true;
            $deviceData['trusted_at'] = now()->toISOString();
            Cache::put($cacheKey, $deviceData, now()->days(self::CACHE_DAYS));

            // Update user session
            \App\Models\UserSession::where('user_id', $userId)
                ->where('device_fingerprint', $fingerprint)
                ->update(['is_trusted' => true]);

            return true;
        }

        return false;
    }

    /**
     * Get all devices for a user
     */
    public function getUserDevices(int $userId): array
    {
        $pattern = "device_fingerprint:user:{$userId}:*";
        $devices = [];

        // Get all cached fingerprints for user
        foreach (Cache::getMultiple($pattern) as $key => $deviceData) {
            if ($deviceData) {
                $devices[] = $deviceData;
            }
        }

        // Also get from user sessions table
        $sessions = \App\Models\UserSession::where('user_id', $userId)
            ->where('last_active_at', '>=', now()->subDays(30))
            ->orderByDesc('last_active_at')
            ->get(['device_fingerprint', 'device_data', 'first_seen', 'last_active_at', 'is_trusted']);

        return $sessions->map(function ($session) {
            return [
                'fingerprint' => $session->device_fingerprint,
                'device_data' => $session->device_data ?? [],
                'first_seen' => $session->first_seen?->toISOString(),
                'last_active' => $session->last_active_at?->toISOString(),
                'is_trusted' => $session->is_trusted ?? false,
            ];
        })->toArray();
    }

    /**
     * Revoke device trust
     */
    public function revokeDevice(Request $request, int $userId): bool
    {
        $fingerprint = $this->createFingerprint($request);
        $cacheKey = "device_fingerprint:user:{$userId}:{$fingerprint}";

        Cache::forget($cacheKey);

        \App\Models\UserSession::where('user_id', $userId)
            ->where('device_fingerprint', $fingerprint)
            ->update(['is_trusted' => false]);

        return true;
    }

    /**
     * Revoke all devices for user
     */
    public function revokeAllDevices(int $userId): int
    {
        $pattern = "device_fingerprint:user:{$userId}:*";
        
        // Remove from cache
        foreach (Cache::getMultiple($pattern) as $key => $value) {
            Cache::forget($key);
        }

        // Update sessions
        $count = \App\Models\UserSession::where('user_id', $userId)
            ->update(['is_trusted' => false]);

        return $count;
    }

    /**
     * Store device data
     */
    private function storeDeviceData(string $fingerprint, array $components, Request $request): void
    {
        \App\Models\UserSession::create([
            'user_id' => $request->user()?->id,
            'device_fingerprint' => $fingerprint,
            'device_data' => $components,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'first_seen' => now(),
            'last_active_at' => now(),
            'is_trusted' => false,
        ]);
    }

    /**
     * Check if device data exists
     */
    private function hasDeviceData(string $fingerprint): bool
    {
        return \App\Models\UserSession::where('device_fingerprint', $fingerprint)->exists();
    }

    /**
     * Log suspicious device activity
     */
    private function logSuspiciousDevice(int $userId, string $fingerprint, Request $request): void
    {
        \App\Models\SecurityEvent::create([
            'user_id' => $userId,
            'event_type' => 'suspicious_device',
            'severity' => 'warning',
            'source' => 'device_fingerprint',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => "User {$userId} has too many devices (5+). New device fingerprint: {$fingerprint}",
            'event_data' => [
                'device_fingerprint' => $fingerprint,
                'device_count' => \App\Models\UserSession::where('user_id', $userId)
                    ->distinct('device_fingerprint')
                    ->count('device_fingerprint'),
            ],
            'detected_at' => now(),
        ]);

        Log::warning('Suspicious device activity detected', [
            'user_id' => $userId,
            'fingerprint' => $fingerprint,
        ]);
    }
}
