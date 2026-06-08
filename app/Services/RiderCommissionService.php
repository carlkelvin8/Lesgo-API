<?php

namespace App\Services;

use App\Models\DriverProfile;

class RiderCommissionService
{
    public const BASIC_RATE = 0.70;
    public const ADVANCE_RATE = 0.80;
    public const PRO_RATE = 0.90;

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
