<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate Limit Service Provider
 * Configures rate limiting rules for different endpoints
 */
class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Global API rate limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please slow down.',
                    ], 429, $headers);
                });
        });

        // Authentication endpoints (stricter)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many login attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        // OTP/Verification endpoints (very strict)
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->input('phone') ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many OTP requests. Please wait before trying again.',
                    ], 429, $headers);
                });
        });

        // Order creation (moderate)
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many order requests. Please slow down.',
                    ], 429, $headers);
                });
        });

        // Payment endpoints (strict)
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many payment requests. Please wait.',
                    ], 429, $headers);
                });
        });

        // Search/Browse endpoints (lenient)
        RateLimiter::for('browse', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        // File uploads (moderate)
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many upload requests. Please wait.',
                    ], 429, $headers);
                });
        });

        // Admin endpoints (lenient for authenticated admins)
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(200)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Webhook endpoints (very lenient)
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(1000)
                ->by($request->ip());
        });
    }
}
