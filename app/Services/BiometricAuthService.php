<?php

namespace App\Services;

use App\Models\User;
use App\Models\BiometricAuth;
use Illuminate\Support\Facades\Hash;

class BiometricAuthService
{
    private SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Enroll biometric authentication
     */
    public function enrollBiometric(User $user, array $data): BiometricAuth
    {
        $biometric = BiometricAuth::create([
            'user_id' => $user->id,
            'biometric_type' => $data['biometric_type'],
            'device_id' => $data['device_id'],
            'biometric_hash' => Hash::make($data['biometric_template']),
            'public_key' => $data['public_key'] ?? null,
            'enrolled_at' => now(),
            'device_info' => $data['device_info'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->securityService->logAuditEvent([
            'user_id' => $user->id,
            'event_type' => 'biometric_enrolled',
            'event_category' => 'authentication',
            'action' => 'create',
            'resource_type' => 'biometric_auth',
            'resource_id' => $biometric->id,
            'risk_level' => 'medium',
            'context' => [
                'biometric_type' => $data['biometric_type'],
                'device_id' => $data['device_id'],
            ],
        ]);

        return $biometric;
    }

    /**
     * Verify biometric authentication
     */
    public function verifyBiometric(User $user, array $data): bool
    {
        $biometric = BiometricAuth::where('user_id', $user->id)
            ->where('biometric_type', $data['biometric_type'])
            ->where('device_id', $data['device_id'])
            ->where('is_active', true)
            ->first();

        if (!$biometric) {
            $this->securityService->logSecurityEvent([
                'user_id' => $user->id,
                'event_type' => 'biometric_not_found',
                'severity' => 'warning',
                'source' => 'authentication',
                'description' => 'Biometric authentication not found for user',
                'event_data' => [
                    'biometric_type' => $data['biometric_type'],
                    'device_id' => $data['device_id'],
                ],
            ]);
            return false;
        }

        // Check if biometric is expired
        if ($biometric->isExpired()) {
            $this->securityService->logSecurityEvent([
                'user_id' => $user->id,
                'event_type' => 'biometric_expired',
                'severity' => 'warning',
                'source' => 'authentication',
                'description' => 'Expired biometric authentication attempt',
                'event_data' => [
                    'biometric_id' => $biometric->id,
                    'last_used' => $biometric->last_used_at,
                ],
            ]);
            return false;
        }

        // Verify biometric template
        if (Hash::check($data['biometric_template'], $biometric->biometric_hash)) {
            $biometric->recordUsage();

            $this->securityService->logAuditEvent([
                'user_id' => $user->id,
                'event_type' => 'biometric_verified',
                'event_category' => 'authentication',
                'action' => 'read',
                'resource_type' => 'biometric_auth',
                'resource_id' => $biometric->id,
                'risk_level' => 'low',
                'context' => [
                    'biometric_type' => $data['biometric_type'],
                    'device_id' => $data['device_id'],
                ],
            ]);

            return true;
        }

        $this->securityService->logSecurityEvent([
            'user_id' => $user->id,
            'event_type' => 'biometric_verification_failed',
            'severity' => 'warning',
            'source' => 'authentication',
            'description' => 'Failed biometric verification attempt',
            'event_data' => [
                'biometric_type' => $data['biometric_type'],
                'device_id' => $data['device_id'],
                'biometric_id' => $biometric->id,
            ],
        ]);

        return false;
    }

    /**
     * Get user's biometric authentications
     */
    public function getUserBiometrics(User $user): array
    {
        return BiometricAuth::where('user_id', $user->id)
            ->where('is_active', true)
            ->select(['id', 'biometric_type', 'device_id', 'enrolled_at', 'last_used_at', 'usage_count', 'device_info'])
            ->get()
            ->toArray();
    }

    /**
     * Deactivate biometric authentication
     */
    public function deactivateBiometric(User $user, int $biometricId): bool
    {
        $biometric = BiometricAuth::where('user_id', $user->id)
            ->where('id', $biometricId)
            ->first();

        if ($biometric) {
            $biometric->deactivate();

            $this->securityService->logAuditEvent([
                'user_id' => $user->id,
                'event_type' => 'biometric_deactivated',
                'event_category' => 'authentication',
                'action' => 'update',
                'resource_type' => 'biometric_auth',
                'resource_id' => $biometric->id,
                'risk_level' => 'medium',
                'context' => [
                    'biometric_type' => $biometric->biometric_type,
                    'device_id' => $biometric->device_id,
                ],
            ]);

            return true;
        }

        return false;
    }

    /**
     * Clean up expired biometrics
     */
    public function cleanupExpiredBiometrics(): int
    {
        $expiredBiometrics = BiometricAuth::where('is_active', true)
            ->where('last_used_at', '<', now()->subDays(90))
            ->get();

        $count = 0;
        foreach ($expiredBiometrics as $biometric) {
            $biometric->deactivate();
            $count++;

            $this->securityService->logAuditEvent([
                'user_id' => $biometric->user_id,
                'event_type' => 'biometric_auto_expired',
                'event_category' => 'system',
                'action' => 'update',
                'resource_type' => 'biometric_auth',
                'resource_id' => $biometric->id,
                'risk_level' => 'low',
                'context' => [
                    'reason' => 'auto_cleanup',
                    'last_used' => $biometric->last_used_at,
                ],
            ]);
        }

        return $count;
    }

    /**
     * Check if user has biometric authentication enabled
     */
    public function hasBiometricEnabled(User $user): bool
    {
        return BiometricAuth::where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}