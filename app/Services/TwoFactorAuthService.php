<?php

namespace App\Services;

use App\Models\User;
use App\Models\TwoFactorAuth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthService
{
    private Google2FA $google2fa;
    private SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->google2fa = new Google2FA();
        $this->securityService = $securityService;
    }

    /**
     * Enable TOTP 2FA for user
     */
    public function enableTotp(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();
        
        $twoFactor = TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id, 'method' => 'totp'],
            [
                'secret' => encrypt($secret),
                'is_enabled' => false, // Will be enabled after verification
            ]
        );

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $this->securityService->logAuditEvent([
            'user_id' => $user->id,
            'event_type' => '2fa_setup_initiated',
            'event_category' => 'authentication',
            'action' => 'create',
            'resource_type' => 'two_factor_auth',
            'resource_id' => $twoFactor->id,
            'risk_level' => 'medium',
        ]);

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'backup_codes' => $twoFactor->generateBackupCodes(),
        ];
    }

    /**
     * Verify and enable TOTP 2FA
     */
    public function verifyAndEnableTotp(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)
            ->where('method', 'totp')
            ->first();

        if (!$twoFactor) {
            return false;
        }

        $secret = decrypt($twoFactor->secret);
        
        if ($this->google2fa->verifyKey($secret, $code)) {
            $twoFactor->update([
                'is_enabled' => true,
                'enabled_at' => now(),
            ]);

            $this->securityService->logAuditEvent([
                'user_id' => $user->id,
                'event_type' => '2fa_enabled',
                'event_category' => 'authentication',
                'action' => 'update',
                'resource_type' => 'two_factor_auth',
                'resource_id' => $twoFactor->id,
                'risk_level' => 'low',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Verify TOTP code
     */
    public function verifyTotp(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)
            ->where('method', 'totp')
            ->where('is_enabled', true)
            ->first();

        if (!$twoFactor) {
            return false;
        }

        $secret = decrypt($twoFactor->secret);
        
        if ($this->google2fa->verifyKey($secret, $code)) {
            $twoFactor->update(['last_used_at' => now()]);

            $this->securityService->logAuditEvent([
                'user_id' => $user->id,
                'event_type' => '2fa_verified',
                'event_category' => 'authentication',
                'action' => 'read',
                'resource_type' => 'two_factor_auth',
                'resource_id' => $twoFactor->id,
                'risk_level' => 'low',
            ]);

            return true;
        }

        $this->securityService->logSecurityEvent([
            'user_id' => $user->id,
            'event_type' => '2fa_verification_failed',
            'severity' => 'warning',
            'source' => 'authentication',
            'description' => 'Failed 2FA verification attempt',
            'event_data' => ['method' => 'totp'],
        ]);

        return false;
    }

    /**
     * Verify backup code
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)
            ->where('method', 'totp')
            ->where('is_enabled', true)
            ->first();

        if (!$twoFactor) {
            return false;
        }

        if ($twoFactor->useBackupCode($code)) {
            $this->securityService->logAuditEvent([
                'user_id' => $user->id,
                'event_type' => '2fa_backup_code_used',
                'event_category' => 'authentication',
                'action' => 'update',
                'resource_type' => 'two_factor_auth',
                'resource_id' => $twoFactor->id,
                'risk_level' => 'medium',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Disable 2FA for user
     */
    public function disable2FA(User $user, string $method = 'totp'): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)
            ->where('method', $method)
            ->first();

        if ($twoFactor) {
            $twoFactor->delete();

            $this->securityService->logAuditEvent([
                'user_id' => $user->id,
                'event_type' => '2fa_disabled',
                'event_category' => 'authentication',
                'action' => 'delete',
                'resource_type' => 'two_factor_auth',
                'resource_id' => $twoFactor->id,
                'risk_level' => 'high',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if user has 2FA enabled
     */
    public function has2FAEnabled(User $user): bool
    {
        return TwoFactorAuth::where('user_id', $user->id)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * Get user's 2FA methods
     */
    public function get2FAMethods(User $user): array
    {
        return TwoFactorAuth::where('user_id', $user->id)
            ->where('is_enabled', true)
            ->pluck('method')
            ->toArray();
    }

    /**
     * Generate new backup codes
     */
    public function regenerateBackupCodes(User $user): array
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)
            ->where('method', 'totp')
            ->where('is_enabled', true)
            ->first();

        if (!$twoFactor) {
            return [];
        }

        $codes = $twoFactor->generateBackupCodes();

        $this->securityService->logAuditEvent([
            'user_id' => $user->id,
            'event_type' => '2fa_backup_codes_regenerated',
            'event_category' => 'authentication',
            'action' => 'update',
            'resource_type' => 'two_factor_auth',
            'resource_id' => $twoFactor->id,
            'risk_level' => 'medium',
        ]);

        return $codes;
    }
}