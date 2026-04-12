<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Tier-Based Rate Limiting Service
 * 
 * Provides intelligent rate limiting based on user tiers.
 * Different tiers get different rate limits to ensure fair usage
 * and premium user experience.
 */
class TierRateLimitingService
{
    /**
     * User tier definitions with rate limits
     * 
     * Format: [max_requests, decay_minutes]
     */
    private const TIER_LIMITS = [
        'free' => [
            'global' => [60, 1],           // 60 requests per minute
            'api' => [100, 1],             // 100 API requests per minute
            'orders' => [10, 1],           // 10 orders per minute
            'payments' => [5, 1],          // 5 payments per minute
            'uploads' => [3, 1],           // 3 uploads per minute
            'chat' => [30, 1],             // 30 chat messages per minute
        ],
        'basic' => [
            'global' => [120, 1],          // 120 requests per minute
            'api' => [200, 1],
            'orders' => [20, 1],
            'payments' => [10, 1],
            'uploads' => [10, 1],
            'chat' => [60, 1],
        ],
        'premium' => [
            'global' => [300, 1],          // 300 requests per minute
            'api' => [500, 1],
            'orders' => [50, 1],
            'payments' => [30, 1],
            'uploads' => [30, 1],
            'chat' => [150, 1],
        ],
        'enterprise' => [
            'global' => [1000, 1],         // 1000 requests per minute
            'api' => [2000, 1],
            'orders' => [200, 1],
            'payments' => [100, 1],
            'uploads' => [100, 1],
            'chat' => [500, 1],
        ],
        'admin' => [
            'global' => [10000, 1],        // Very high limit for admins
            'api' => [20000, 1],
            'orders' => [1000, 1],
            'payments' => [500, 1],
            'uploads' => [500, 1],
            'chat' => [5000, 1],
        ],
    ];

    /**
     * Get user's tier
     */
    public function getUserTier($user): string
    {
        if (!$user) {
            return 'free'; // Unauthenticated users
        }

        // Admin role gets admin tier
        if ($user->role === 'admin') {
            return 'admin';
        }

        // Get tier from user profile or subscription
        if (method_exists($user, 'getTier')) {
            return $user->getTier();
        }

        // Fallback to subscription plan or points-based tier
        if (isset($user->subscription_tier)) {
            return $user->subscription_tier;
        }

        // Points-based tier determination
        if (isset($user->points)) {
            if ($user->points >= 10000) {
                return 'enterprise';
            }

            if ($user->points >= 5000) {
                return 'premium';
            }

            if ($user->points >= 1000) {
                return 'basic';
            }
        }

        return 'free';
    }

    /**
     * Check if user has exceeded rate limit
     */
    public function hasExceededLimit(Request $request, $user, string $category = 'api'): bool
    {
        $tier = $this->getUserTier($user);
        $limits = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS['free'];
        
        if (!isset($limits[$category])) {
            return false;
        }

        [$maxAttempts, $decayMinutes] = $limits[$category];
        $key = $this->buildKey($user, $category);

        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Apply rate limit check and return result
     */
    public function checkLimit(Request $request, $user, string $category = 'api'): array
    {
        $tier = $this->getUserTier($user);
        $limits = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS['free'];
        
        if (!isset($limits[$category])) {
            return [
                'allowed' => true,
                'tier' => $tier,
                'category' => $category,
                'limit' => null,
                'remaining' => null,
                'reset' => null,
            ];
        }

        [$maxAttempts, $decayMinutes] = $limits[$category];
        $key = $this->buildKey($user, $category);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $availableIn = RateLimiter::availableIn($key);
            
            return [
                'allowed' => false,
                'tier' => $tier,
                'category' => $category,
                'limit' => $maxAttempts,
                'remaining' => 0,
                'reset' => now()->addSeconds($availableIn)->toISOString(),
                'retry_after' => $availableIn,
            ];
        }

        // Record this attempt
        RateLimiter::hit($key, $decayMinutes * 60);

        $remaining = max(0, $maxAttempts - RateLimiter::attempts($key));
        $resetTime = now()->addMinutes($decayMinutes)->toISOString();

        return [
            'allowed' => true,
            'tier' => $tier,
            'category' => $category,
            'limit' => $maxAttempts,
            'remaining' => $remaining,
            'reset' => $resetTime,
        ];
    }

    /**
     * Get rate limit headers for response
     */
    public function getRateLimitHeaders(Request $request, $user, string $category = 'api'): array
    {
        $result = $this->checkLimit($request, $user, $category);

        return [
            'X-RateLimit-Limit' => $result['limit'] ?? 0,
            'X-RateLimit-Remaining' => $result['remaining'] ?? 0,
            'X-RateLimit-Reset' => $result['reset'] ?? time(),
            'X-User-Tier' => $result['tier'],
        ];
    }

    /**
     * Get user's current rate limit status across all categories
     */
    public function getUserRateLimitStatus($user): array
    {
        $tier = $this->getUserTier($user);
        $limits = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS['free'];

        $status = [];

        foreach ($limits as $category => [$maxAttempts, $decayMinutes]) {
            $key = $this->buildKey($user, $category);
            $attempts = RateLimiter::attempts($key);
            $remaining = max(0, $maxAttempts - $attempts);

            $status[$category] = [
                'limit' => $maxAttempts,
                'used' => $attempts,
                'remaining' => $remaining,
                'utilization_percent' => round(($attempts / $maxAttempts) * 100, 2),
                'window_minutes' => $decayMinutes,
            ];
        }

        return [
            'tier' => $tier,
            'categories' => $status,
        ];
    }

    /**
     * Upgrade user's rate limits temporarily (e.g., for special events)
     */
    public function temporarilyUpgradeTier($user, string $temporaryTier, int $durationMinutes): void
    {
        $cacheKey = "temporary_tier:{$user->id}";
        
        Cache::put($cacheKey, [
            'tier' => $temporaryTier,
            'expires_at' => now()->addMinutes($durationMinutes)->toISOString(),
        ], now()->addMinutes($durationMinutes));
    }

    /**
     * Reset user's rate limits
     */
    public function resetUserLimits($user, ?string $category = null): void
    {
        if ($category) {
            $key = $this->buildKey($user, $category);
            RateLimiter::clear($key);
        } else {
            // Reset all categories
            $tier = $this->getUserTier($user);
            $limits = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS['free'];

            foreach (array_keys($limits) as $cat) {
                $key = $this->buildKey($user, $cat);
                RateLimiter::clear($key);
            }
        }
    }

    /**
     * Build rate limit key
     */
    private function buildKey($user, string $category): string
    {
        $identifier = $user ? "user:{$user->id}" : "ip:" . request()->ip();
        return "rate_limit:{$category}:{$identifier}";
    }

    /**
     * Get all available tiers
     */
    public static function getAvailableTiers(): array
    {
        return array_keys(self::TIER_LIMITS);
    }

    /**
     * Get tier upgrade requirements
     */
    public function getTierUpgradeRequirements(string $currentTier): array
    {
        $requirements = [
            'free' => [
                'next_tier' => 'basic',
                'requirement' => 'Accumulate 1,000 points or subscribe to Basic plan',
                'points_needed' => 1000,
            ],
            'basic' => [
                'next_tier' => 'premium',
                'requirement' => 'Accumulate 5,000 points or subscribe to Premium plan',
                'points_needed' => 5000,
            ],
            'premium' => [
                'next_tier' => 'enterprise',
                'requirement' => 'Accumulate 10,000 points or contact sales for Enterprise plan',
                'points_needed' => 10000,
            ],
            'enterprise' => [
                'next_tier' => null,
                'requirement' => 'Maximum tier reached',
            ],
        ];

        return $requirements[$currentTier] ?? [];
    }
}
