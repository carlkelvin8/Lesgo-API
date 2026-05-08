<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Models\Partner;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Policies\OrderPolicy;
use App\Policies\UserPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\MenuCategoryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureGates();
        $this->configureQueryLogging();
    }

    /**
     * Prevent lazy loading in non-production to catch N+1 early.
     * Disable strict mode in production for safety.
     */
    protected function configureModels(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
    }

    /**
     * Log queries that exceed 500ms in production, 200ms in other envs.
     */
    protected function configureQueryLogging(): void
    {
        $threshold = app()->isProduction() ? 500 : 200;

        DB::listen(function ($query) use ($threshold) {
            if ($query->time > $threshold) {
                Log::warning('Slow query detected', [
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms'  => $query->time,
                ]);
            }
        });
    }
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
            $limit = app()->environment('local') ? 60 : 5;
            return Limit::perMinute($limit)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many authentication attempts. Please try again later.',
                    ], 429);
                });
        });

        // Rate limit for driver registration (3 requests per minute)
        RateLimiter::for('driver-registration', function (Request $request) {
            $limit = app()->environment('local') ? 60 : 3;
            return Limit::perMinute($limit)->by($request->ip())
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
        Gate::policy(Partner::class, PartnerPolicy::class);
        Gate::policy(MenuItem::class, MenuItemPolicy::class);
        Gate::policy(MenuCategory::class, MenuCategoryPolicy::class);
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

