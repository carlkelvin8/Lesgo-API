<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\Order;

class RiderCommissionService
{
    public const BASIC_RATE = 0.70;
    public const ADVANCE_RATE = 0.80;
    public const PRO_RATE = 0.90;

    /**
     * Calculate the shipping fee (booking charge) for an order.
     * This is the amount charged to the driver when accepting a booking.
     */
    public static function resolveShippingFee(Order $order): float
    {
        // The booking charge is typically a percentage of the estimated fare
        // or a flat fee. For now, we'll use 20% of the fare as the booking charge.
        $estimatedFare = (float) ($order->estimated_fare ?? 0);
        
        // Minimum charge of PHP 10, max 20% of fare
        $charge = max(10.0, $estimatedFare * 0.20);
        
        return round($charge, 2);
    }

    /**
     * Apply commission/shares calculation to an order.
     * Calculates driver earnings based on their tier and order fare.
     */
    public static function applySharesToOrder(Order $order, ?DriverProfile $driverProfile): void
    {
        if (!$driverProfile) {
            return;
        }

        $commissionRate = self::resolveCommissionRate($driverProfile);
        $fare = (float) ($order->actual_fare ?? $order->estimated_fare ?? 0);
        
        // Driver gets their commission percentage of the fare
        $driverShare = $fare * $commissionRate;
        
        // Platform gets the rest
        $platformShare = $fare - $driverShare;
        
        $order->driver_share = round($driverShare, 2);
        $order->platform_share = round($platformShare, 2);
    }

    public static function resolveCommissionRate(DriverProfile $profile): float
    {
        $tier = strtolower(trim((string) ($profile->package_tier ?? 'basic')));

        return match ($tier) {
            'advance', 'advanced' => self::ADVANCE_RATE,
            'pro', 'pro_rider', 'professional' => self::PRO_RATE,
            default => self::BASIC_RATE,
        };
    }
}
