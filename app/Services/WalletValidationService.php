<?php

namespace App\Services;

use App\Models\SecuritySetting;
use App\Models\User;
use App\Models\Wallet;

class WalletValidationService
{
    /**
     * Default minimum wallet balance threshold in PHP (required to accept bookings).
     */
    const DEFAULT_THRESHOLD = 11.00;

    /**
     * Minimum maintaining balance riders should keep in wallet.
     */
    const MAINTAINING_BALANCE = 10.00;

    /**
     * Setting key for wallet threshold
     */
    const THRESHOLD_SETTING_KEY = 'wallet.minimum_balance_threshold';

    /**
     * Get the current minimum wallet balance threshold
     */
    public static function getMinimumThreshold(): float
    {
        return (float) SecuritySetting::getValue(
            self::THRESHOLD_SETTING_KEY,
            self::DEFAULT_THRESHOLD
        );
    }

    /**
     * Set the minimum wallet balance threshold
     */
    public static function setMinimumThreshold(float $threshold, string $updatedBy = null): void
    {
        SecuritySetting::setValue(self::THRESHOLD_SETTING_KEY, $threshold, $updatedBy);
    }

    /**
     * Check if a user has sufficient wallet balance
     */
    public static function hasSufficientBalance(User $user): bool
    {
        if (!$user->isDriver()) {
            return true; // Only drivers need wallet validation
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            return false; // No wallet = insufficient balance
        }

        $threshold = self::getMinimumThreshold();
        return $wallet->balance >= $threshold;
    }

    /**
     * Get user's wallet balance
     */
    public static function getWalletBalance(User $user): float
    {
        $wallet = $user->wallet;
        return $wallet ? (float) $wallet->balance : 0.00;
    }

    /**
     * Get validation result with details
     */
    public static function validateBalance(User $user): array
    {
        $balance = self::getWalletBalance($user);
        $threshold = self::getMinimumThreshold();
        $hasSufficient = $balance >= $threshold;

        return [
            'has_sufficient_balance' => $hasSufficient,
            'current_balance' => $balance,
            'minimum_threshold' => $threshold,
            'maintaining_balance' => self::MAINTAINING_BALANCE,
            'shortfall' => $hasSufficient ? 0 : ($threshold - $balance),
        ];
    }

    /**
     * Human-readable message when a driver cannot accept a booking.
     */
    public static function acceptBlockedMessage(User $user): string
    {
        $balance = self::getWalletBalance($user);
        $threshold = self::getMinimumThreshold();

        if ($balance >= $threshold) {
            return '';
        }

        if ($balance <= self::MAINTAINING_BALANCE) {
            return sprintf(
                'Your wallet balance is PHP %.2f. Insufficient balance to accept bookings. Please top up your wallet (minimum PHP %.0f required).',
                $balance,
                $threshold
            );
        }

        return sprintf(
            'Insufficient wallet balance to accept bookings. Please top up to at least PHP %.0f.',
            $threshold
        );
    }

    /**
     * Create wallet for user if it doesn't exist
     */
    public static function ensureWalletExists(User $user): Wallet
    {
        return $user->wallet ?? Wallet::create([
            'user_id' => $user->id,
            'balance' => 0.00,
            'currency' => 'PHP',
        ]);
    }
}