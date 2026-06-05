<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\SecuritySetting;

class RiderCommissionService
{
    public const PACKAGE_BASIC   = 'basic';
    public const PACKAGE_ADVANCE = 'advance';
    public const PACKAGE_PRO     = 'pro';

    public const DEFAULT_RATES = [
        self::PACKAGE_BASIC   => 0.70,
        self::PACKAGE_ADVANCE => 0.80,
        self::PACKAGE_PRO     => 0.90,
    ];

    public const SETTING_PREFIX = 'rider.commission_rate.';

    /**
     * Shipping / delivery fee only — never item subtotals or grand totals.
     */
    public static function resolveShippingFee(Order $order): float
    {
        $order->refresh();
        $meta = is_array($order->meta) ? $order->meta : [];

        foreach (['shipping_fee', 'delivery_fee', 'fare', 'delivery_charge'] as $key) {
            if (!empty($meta[$key])) {
                return round(max(0, (float) $meta[$key]), 2);
            }
        }

        if (is_array($order->fare_breakdown ?? null)) {
            $breakdown = $order->fare_breakdown;
            if (!empty($breakdown['total'])) {
                return round(max(0, (float) $breakdown['total']), 2);
            }
        }

        $fare = (float) ($order->actual_fare ?? $order->estimated_fare ?? 0);

        return round(max(0, $fare), 2);
    }

    public static function normalizePackageTier(?string $tier): string
    {
        $tier = strtolower(trim((string) $tier));

        return match ($tier) {
            self::PACKAGE_ADVANCE, 'advanced' => self::PACKAGE_ADVANCE,
            self::PACKAGE_PRO, 'pro_rider', 'professional' => self::PACKAGE_PRO,
            default => self::PACKAGE_BASIC,
        };
    }

    public static function getConfiguredRates(): array
    {
        $rates = [];

        foreach (self::DEFAULT_RATES as $package => $default) {
            $rates[$package] = (float) SecuritySetting::getValue(
                self::SETTING_PREFIX . $package,
                $default
            );
        }

        return $rates;
    }

    public static function setConfiguredRate(string $package, float $rate, ?string $updatedBy = null): void
    {
        $package = self::normalizePackageTier($package);
        $rate    = max(0, min(1, $rate));

        SecuritySetting::setValue(
            self::SETTING_PREFIX . $package,
            $rate,
            $updatedBy
        );
    }

    public static function resolveCommissionRate(?DriverProfile $profile): float
    {
        $package = self::normalizePackageTier($profile?->package_tier);
        $rates   = self::getConfiguredRates();

        return (float) ($rates[$package] ?? self::DEFAULT_RATES[self::PACKAGE_BASIC]);
    }

    /**
     * @return array{
     *   shipping_fee: float,
     *   commission_rate: float,
     *   package_tier: string,
     *   driver_share: float,
     *   platform_fee: float,
     *   partner_share: float
     * }
     */
    public static function calculateShares(Order $order, ?DriverProfile $profile = null): array
    {
        $order->loadMissing('service');
        $shippingFee     = self::resolveShippingFee($order);
        $commissionRate  = self::resolveCommissionRate($profile);
        $packageTier     = self::normalizePackageTier($profile?->package_tier);
        $driverShare     = round($shippingFee * $commissionRate, 2);
        $platformFee     = round(max(0, $shippingFee - $driverShare), 2);
        $partnerShare    = 0.0;

        // Partner share stays on shipping fee only when applicable.
        if ($order->partner_id && in_array(strtoupper((string) optional($order->service)->code), ['LESBUY', 'LESEAT'], true)) {
            $partnerRate  = 0.05;
            $partnerShare = round($shippingFee * $partnerRate, 2);
            $driverShare  = round(max(0, $driverShare - $partnerShare), 2);
            $platformFee  = round(max(0, $shippingFee - $driverShare - $partnerShare), 2);
        }

        return [
            'shipping_fee'    => $shippingFee,
            'commission_rate' => $commissionRate,
            'package_tier'    => $packageTier,
            'driver_share'    => $driverShare,
            'platform_fee'    => $platformFee,
            'partner_share'   => $partnerShare,
        ];
    }

    public static function applySharesToOrder(Order $order, ?DriverProfile $profile = null): array
    {
        $shares = self::calculateShares($order, $profile);

        $order->platform_fee = $shares['platform_fee'];
        $order->driver_share = $shares['driver_share'];
        $order->partner_share = $shares['partner_share'];

        $meta = is_array($order->meta) ? $order->meta : [];
        $meta['shipping_fee']           = $shares['shipping_fee'];
        $meta['driver_commission_rate'] = $shares['commission_rate'];
        $meta['driver_package_tier']    = $shares['package_tier'];
        $meta['wallet_charge_basis']    = 'shipping_fee_only';
        $order->meta = $meta;

        return $shares;
    }
}
