<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class EncryptionService
{
    /**
     * Encrypt sensitive data before storing in database.
     */
    public static function encryptSensitive(string $data): string
    {
        return Crypt::encryptString($data);
    }

    /**
     * Decrypt sensitive data when retrieving from database.
     */
    public static function decryptSensitive(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    /**
     * Hash sensitive data (one-way, for comparison only).
     */
    public static function hashSensitive(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Mask sensitive data for display (e.g., phone numbers, emails).
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];
        
        $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
        
        return $maskedName . '@' . $domain;
    }

    /**
     * Mask phone number for display.
     */
    public static function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length < 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($phone, -4);
    }

    /**
     * Generate secure random token.
     */
    public static function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
