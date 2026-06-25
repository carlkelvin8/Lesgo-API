<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\SecuritySetting;

class RiderCommissionService
{
    public const PACKAGE_BASIC = 'basic';
    public const PACKAGE_ADVANCE = 'advance';
    public const PACKAGE_PRO = 'pro';
    public const PACKAGE_EMPLOYEE = 'employee';

    public const BASIC_RATE = 0.70;
    public const ADVANCE_RATE = 0.80;
    public const PRO_RATE = 0.90;

    private const RATE_SETTING_KEYS = [
        self::PACKAGE_BASIC => 'rider.commission.basic',
        self::PACKAGE_ADVANCE => 'rider.commission.advance',
        self::PACKAGE_PRO => 'rider.commission.pro',
    ];

    private const PRICE_SETTING_KEYS = [
        self::PACKAGE_ADVANCE => 'rider.package.price.advance',
        self::PACKAGE_PRO => 'rider.package.price.pro',
    ];

    private const DEFAULT_RATES = [
        self::PACKAGE_BASIC => self::BASIC_RATE,
        self::PACKAGE_ADVANCE => self::ADVANCE_RATE,
        self::PACKAGE_PRO => self::PRO_RATE,
    ];

    /** One-time upgrade prices (PHP) — admin can override via wallet settings. */
    private const DEFAULT_PRICES = [
        self::PACKAGE_ADVANCE => 999.00,
        self::PACKAGE_PRO => 1999.00,
    ];

    public static function resolveShippingFee(Order $order): float
    {
        $estimatedFare = (float) ($order->estimated_fare ?? 0);

        return round(max(10.0, $estimatedFare * 0.20), 2);
    }

    public static function applySharesToOrder(Order $order, ?DriverProfile $driverProfile): void
    {
        if (!$driverProfile) {
            return;
        }

        $commissionRate = self::resolveCommissionRate($driverProfile);
        $fare = (float) ($order->actual_fare ?? $order->estimated_fare ?? 0);
        $driverShare = $fare * $commissionRate;
        $platformShare = $fare - $driverShare;

        $order->driver_share = round($driverShare, 2);
        $order->platform_share = round($platformShare, 2);
    }

    public static function resolveCommissionRate(DriverProfile $profile): float
    {
        $tier = self::normalizeTier((string) ($profile->package_tier ?? self::PACKAGE_BASIC));

        return self::getConfiguredRates()[$tier] ?? self::BASIC_RATE;
    }

    public static function normalizeTier(string $raw): string
    {
        $tier = strtolower(trim($raw));

        return match ($tier) {
            'advance', 'advanced', 'premium' => self::PACKAGE_ADVANCE,
            'pro', 'pro_rider', 'professional', 'elite' => self::PACKAGE_PRO,
            'employee' => self::PACKAGE_EMPLOYEE,
            default => self::PACKAGE_BASIC,
        };
    }

    public static function labelForTier(string $tier): string
    {
        return match (self::normalizeTier($tier)) {
            self::PACKAGE_ADVANCE => 'Advance',
            self::PACKAGE_PRO => 'Pro',
            self::PACKAGE_EMPLOYEE => 'Employee',
            default => 'Basic',
        };
    }

    public static function tierRank(string $tier): int
    {
        return match (self::normalizeTier($tier)) {
            self::PACKAGE_ADVANCE => 2,
            self::PACKAGE_PRO => 3,
            self::PACKAGE_EMPLOYEE => 4,
            default => 1,
        };
    }

    public static function canUpgradeTo(string $currentTier, string $targetTier): bool
    {
        $current = self::normalizeTier($currentTier);
        $target = self::normalizeTier($targetTier);

        if ($current === self::PACKAGE_EMPLOYEE || $target === self::PACKAGE_EMPLOYEE) {
            return false;
        }

        if ($target === self::PACKAGE_BASIC) {
            return false;
        }

        return self::tierRank($target) > self::tierRank($current);
    }

    public static function getConfiguredRates(): array
    {
        return [
            self::PACKAGE_BASIC => self::readRate(self::PACKAGE_BASIC),
            self::PACKAGE_ADVANCE => self::readRate(self::PACKAGE_ADVANCE),
            self::PACKAGE_PRO => self::readRate(self::PACKAGE_PRO),
        ];
    }

    public static function setConfiguredRate(string $tier, float $rate, ?string $updatedBy = null): void
    {
        $tier = self::normalizeTier($tier);
        $key = self::RATE_SETTING_KEYS[$tier] ?? null;

        if (!$key) {
            return;
        }

        SecuritySetting::setValue($key, round($rate, 4), $updatedBy);
    }

    public static function getPackagePrices(): array
    {
        return [
            self::PACKAGE_BASIC => 0.0,
            self::PACKAGE_ADVANCE => self::readPrice(self::PACKAGE_ADVANCE),
            self::PACKAGE_PRO => self::readPrice(self::PACKAGE_PRO),
        ];
    }

    public static function getPackagePrice(string $tier): float
    {
        $tier = self::normalizeTier($tier);

        return self::getPackagePrices()[$tier] ?? 0.0;
    }

    public static function setPackagePrice(string $tier, float $price, ?string $updatedBy = null): void
    {
        $tier = self::normalizeTier($tier);
        $key = self::PRICE_SETTING_KEYS[$tier] ?? null;

        if (!$key) {
            return;
        }

        SecuritySetting::setValue($key, round(max(0, $price), 2), $updatedBy);
    }

    public static function packageCatalog(?string $currentTier = null): array
    {
        $rates = self::getConfiguredRates();
        $prices = self::getPackagePrices();
        $current = self::normalizeTier($currentTier ?? self::PACKAGE_BASIC);

        $packages = [
            [
                'tier' => self::PACKAGE_BASIC,
                'label' => 'Basic',
                'commission_rate' => $rates[self::PACKAGE_BASIC],
                'commission_percent' => round($rates[self::PACKAGE_BASIC] * 100, 2),
                'profit_share_label' => '70/30 profit share (you keep 70%)',
                'one_time_price' => 0.0,
                'is_current' => $current === self::PACKAGE_BASIC,
                'is_upgradeable' => false,
            ],
            [
                'tier' => self::PACKAGE_ADVANCE,
                'label' => 'Advance',
                'commission_rate' => $rates[self::PACKAGE_ADVANCE],
                'commission_percent' => round($rates[self::PACKAGE_ADVANCE] * 100, 2),
                'profit_share_label' => '80/20 profit share (you keep 80%)',
                'one_time_price' => $prices[self::PACKAGE_ADVANCE],
                'is_current' => $current === self::PACKAGE_ADVANCE,
                'is_upgradeable' => self::canUpgradeTo($current, self::PACKAGE_ADVANCE),
            ],
            [
                'tier' => self::PACKAGE_PRO,
                'label' => 'Pro',
                'commission_rate' => $rates[self::PACKAGE_PRO],
                'commission_percent' => round($rates[self::PACKAGE_PRO] * 100, 2),
                'profit_share_label' => '90/10 profit share (you keep 90%)',
                'one_time_price' => $prices[self::PACKAGE_PRO],
                'is_current' => $current === self::PACKAGE_PRO,
                'is_upgradeable' => self::canUpgradeTo($current, self::PACKAGE_PRO),
            ],
            [
                'tier' => self::PACKAGE_EMPLOYEE,
                'label' => 'Employee',
                'commission_rate' => null,
                'commission_percent' => null,
                'profit_share_label' => 'Fixed daily salary',
                'one_time_price' => null,
                'is_current' => $current === self::PACKAGE_EMPLOYEE,
                'is_upgradeable' => false,
            ],
        ];

        return $packages;
    }

    private static function readRate(string $tier): float
    {
        $key = self::RATE_SETTING_KEYS[$tier] ?? null;
        $default = self::DEFAULT_RATES[$tier] ?? self::BASIC_RATE;

        if (!$key) {
            return $default;
        }

        $value = SecuritySetting::getValue($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    private static function readPrice(string $tier): float
    {
        $key = self::PRICE_SETTING_KEYS[$tier] ?? null;
        $default = self::DEFAULT_PRICES[$tier] ?? 0.0;

        if (!$key) {
            return $default;
        }

        $value = SecuritySetting::getValue($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }
}
