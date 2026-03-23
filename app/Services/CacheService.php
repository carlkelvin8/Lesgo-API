<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache durations in seconds
     */
    const CACHE_SHORT = 300;      // 5 minutes
    const CACHE_MEDIUM = 1800;    // 30 minutes
    const CACHE_LONG = 3600;      // 1 hour
    const CACHE_DAY = 86400;      // 24 hours

    /**
     * Cache a value with automatic key generation
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Clear cache by key
     */
    public static function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Clear cache by pattern (Redis only — silently skipped for other drivers)
     */
    public static function forgetByPattern(string $pattern): void
    {
        try {
            $store = Cache::getStore();

            // Only attempt pattern deletion on Redis
            if (!($store instanceof \Illuminate\Cache\RedisStore)) {
                return;
            }

            $redis = Cache::getRedis();
            $prefix = config('cache.prefix') ? config('cache.prefix') . ':' : '';
            $keys = $redis->keys($prefix . $pattern);

            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Throwable) {
            // Non-critical — pattern cache busting is best-effort
        }
    }

    /**
     * Clear all service caches
     */
    public static function clearServiceCache(): void
    {
        self::forgetByPattern('*services:*');
        self::forgetByPattern('*service:*');
    }

    /**
     * Clear all order caches
     */
    public static function clearOrderCache(): void
    {
        self::forgetByPattern('*orders:*');
        self::forgetByPattern('*order:*');
    }

    /**
     * Clear all payment caches
     */
    public static function clearPaymentCache(): void
    {
        self::forgetByPattern('*payments:*');
        self::forgetByPattern('*payment:*');
    }

    /**
     * Clear wallet caches for a user
     */
    public static function clearWalletCache(int $userId): void
    {
        self::forgetByPattern("*wallets:user:{$userId}:*");
    }

    /**
     * Clear user-specific cache
     */
    public static function clearUserCache(int $userId): void
    {
        self::forgetByPattern("*user:{$userId}:*");
    }

    /**
     * Clear all application cache
     */
    public static function clearAll(): void
    {
        Cache::flush();
    }

    /**
     * Get cache statistics (Redis only)
     */
    public static function getStats(): array
    {
        try {
            $redis = Cache::getRedis();
            $info = $redis->info();
            
            return [
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => self::calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Unable to get cache stats'];
        }
    }

    /**
     * Calculate cache hit rate
     */
    private static function calculateHitRate(array $info): string
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return '0%';
        }

        $rate = ($hits / $total) * 100;
        return number_format($rate, 2) . '%';
    }
}
