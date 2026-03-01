<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureGates();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // General API rate limit (60 requests per minute)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please slow down.',
                    ], 429);
                });
        });

        // Stricter rate limit for authentication endpoints (5 requests per minute)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many authentication attempts. Please try again later.',
                    ], 429);
                });
        });

        // Rate limit for driver registration (3 requests per minute)
        RateLimiter::for('driver-registration', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many registration attempts. Please try again later.',
                    ], 429);
                });
        });

        // Higher rate limit for authenticated users (120 requests per minute)
        RateLimiter::for('authenticated', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Rate limit exceeded.',
                    ], 429);
                });
        });

        // Very strict for sensitive operations (password reset, etc.)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many attempts. Please try again later.',
                    ], 429);
                });
        });
    }

    /**
     * Configure model policies.
     */
    protected function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
    }

    /**
     * Configure authorization gates.
     */
    protected function configureGates(): void
    {
        // Admin gate
        Gate::define('admin', function (User $user) {
            return $user->role === 'admin';
        });

        // Employer gate
        Gate::define('employer', function (User $user) {
            return in_array($user->role, ['employer', 'admin']);
        });

        // Driver gate
        Gate::define('driver', function (User $user) {
            return in_array($user->role, ['driver', 'admin']);
        });

        // Partner admin gate
        Gate::define('partner-admin', function (User $user) {
            return in_array($user->role, ['partner_admin', 'admin']);
        });
    }
}

