<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Centralized Cache Service
 * Provides consistent caching strategy across the application
 */
class CacheService
{
    /**
     * Cache TTL constants (in seconds)
     */
    const TTL_SHORT = 300;      // 5 minutes
    const TTL_MEDIUM = 1800;    // 30 minutes
    const TTL_LONG = 3600;      // 1 hour
    const TTL_VERY_LONG = 86400; // 24 hours

    /** Aliases used by controllers that call CacheService statically */
    const CACHE_SHORT  = self::TTL_SHORT;
    const CACHE_MEDIUM = self::TTL_MEDIUM;
    const CACHE_LONG   = self::TTL_LONG;

    /**
     * Cache key prefixes
     */
    const PREFIX_USER = 'user:';
    const PREFIX_PARTNER = 'partner:';
    const PREFIX_ORDER = 'order:';
    const PREFIX_MENU = 'menu:';
    const PREFIX_SERVICE = 'service:';
    const PREFIX_ADDRESS = 'address:';
    const PREFIX_DRIVER = 'driver:';

    /**
     * Remember a value in cache (static — callable as CacheService::remember(...)).
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache remember failed', ['key' => $key, 'error' => $e->getMessage()]);
            return $callback();
        }
    }

    /**
     * Forget all cache entries whose key matches a glob-style pattern.
     * Works with Redis (SCAN + DEL); silently no-ops on other drivers.
     */
    public static function forgetByPattern(string $pattern): void
    {
        try {
            $store = Cache::getStore();

            // Redis store: use SCAN to find matching keys and delete them.
            if ($store instanceof \Illuminate\Cache\RedisStore) {
                $redis  = $store->connection();
                $prefix = $store->getPrefix();
                $cursor = '0';
                do {
                    [$cursor, $keys] = $redis->scan($cursor, 'MATCH', $prefix . $pattern, 'COUNT', 100);
                    foreach ($keys as $key) {
                        $redis->del($key);
                    }
                } while ($cursor !== '0');
                return;
            }

            // For non-Redis drivers, forget the exact key if no wildcard used.
            if (strpos($pattern, '*') === false) {
                Cache::forget($pattern);
            }
        } catch (\Exception $e) {
            Log::warning('Cache forgetByPattern failed', ['pattern' => $pattern, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get a value from cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            Log::warning('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * Put a value in cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function put(string $key, mixed $value, int $ttl): bool
    {
        try {
            return Cache::put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::warning('Cache put failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Forget a value from cache
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning('Cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Flush cache by prefix/tag
     *
     * @param string $prefix
     * @return bool
     */
    public function flushByPrefix(string $prefix): bool
    {
        try {
            // Get all keys with prefix
            $keys = Cache::get('cache_keys:' . $prefix, []);

            foreach ($keys as $key) {
                Cache::forget($key);
            }

            Cache::forget('cache_keys:' . $prefix);

            return true;
        } catch (\Exception $e) {
            Log::warning('Cache flush by prefix failed', [
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cache user data
     *
     * @param int $userId
     * @param callable $callback
     * @return mixed
     */
    public function cacheUser(int $userId, callable $callback): mixed
    {
        $key = self::PREFIX_USER . $userId;
        return $this->remember($key, self::TTL_MEDIUM, $callback);
    }

    /**
     * Cache partner data
     *
     * @param int $partnerId
     * @param callable $callback
     * @return mixed
     */
    public function cachePartner(int $partnerId, callable $callback): mixed
    {
        $key = self::PREFIX_PARTNER . $partnerId;
        return $this->remember($key, self::TTL_LONG, $callback);
    }

    /**
     * Cache partner menu
     *
     * @param int $partnerId
     * @param callable $callback
     * @return mixed
     */
    public function cachePartnerMenu(int $partnerId, callable $callback): mixed
    {
        $key = self::PREFIX_MENU . $partnerId;
        return $this->remember($key, self::TTL_MEDIUM, $callback);
    }

    /**
     * Cache order data
     *
     * @param int $orderId
     * @param callable $callback
     * @return mixed
     */
    public function cacheOrder(int $orderId, callable $callback): mixed
    {
        $key = self::PREFIX_ORDER . $orderId;
        return $this->remember($key, self::TTL_SHORT, $callback);
    }

    /**
     * Cache services list
     *
     * @param callable $callback
     * @return mixed
     */
    public function cacheServices(callable $callback): mixed
    {
        $key = self::PREFIX_SERVICE . 'all';
        return $this->remember($key, self::TTL_VERY_LONG, $callback);
    }

    /**
     * Invalidate user cache
     *
     * @param int $userId
     * @return bool
     */
    public function invalidateUser(int $userId): bool
    {
        return $this->forget(self::PREFIX_USER . $userId);
    }

    /**
     * Invalidate partner cache
     *
     * @param int $partnerId
     * @return bool
     */
    public function invalidatePartner(int $partnerId): bool
    {
        $this->forget(self::PREFIX_PARTNER . $partnerId);
        $this->forget(self::PREFIX_MENU . $partnerId);
        return true;
    }

    /**
     * Invalidate order cache
     *
     * @param int $orderId
     * @return bool
     */
    public function invalidateOrder(int $orderId): bool
    {
        return $this->forget(self::PREFIX_ORDER . $orderId);
    }

    /**
     * Invalidate all services cache
     *
     * @return bool
     */
    public function invalidateServices(): bool
    {
        return $this->forget(self::PREFIX_SERVICE . 'all');
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        try {
            return [
                'driver' => config('cache.default'),
                'enabled' => true,
                'ttl_short' => self::TTL_SHORT,
                'ttl_medium' => self::TTL_MEDIUM,
                'ttl_long' => self::TTL_LONG,
                'ttl_very_long' => self::TTL_VERY_LONG,
            ];
        } catch (\Exception $e) {
            return [
                'driver' => 'unknown',
                'enabled' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
