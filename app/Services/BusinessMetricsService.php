<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\DriverProfile;
use App\Models\Wallet;
use App\Models\RatingReview;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Business Metrics Collection Service
 * 
 * Collects and aggregates business metrics for dashboards and reporting.
 * Provides real-time and historical metrics for monitoring business health.
 */
class BusinessMetricsService
{
    /**
     * Cache duration for metrics (5 minutes)
     */
    private const CACHE_DURATION = 300;

    /**
     * Get comprehensive business metrics dashboard
     */
    public function getDashboard(): array
    {
        return [
            'overview' => $this->getOverviewMetrics(),
            'orders' => $this->getOrderMetrics(),
            'revenue' => $this->getRevenueMetrics(),
            'users' => $this->getUserMetrics(),
            'drivers' => $this->getDriverMetrics(),
            'customer_satisfaction' => $this->getSatisfactionMetrics(),
            'support' => $this->getSupportMetrics(),
            'system_performance' => $this->getSystemPerformanceMetrics(),
        ];
    }

    /**
     * Get overview metrics
     */
    public function getOverviewMetrics(): array
    {
        return Cache::remember('metrics:overview', self::CACHE_DURATION, function () {
            $today = now()->startOfDay();
            $yesterday = now()->subDay()->startOfDay();

            $todayOrders = Order::whereDate('created_at', $today)->count();
            $yesterdayOrders = Order::whereDate('created_at', $yesterday)->count();
            $orderGrowth = $yesterdayOrders > 0 
                ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 2) 
                : 0;

            $todayRevenue = Order::whereDate('created_at', $today)
                ->where('status', 'completed')
                ->sum('estimated_fare');
            $yesterdayRevenue = Order::whereDate('created_at', $yesterday)
                ->where('status', 'completed')
                ->sum('estimated_fare');
            $revenueGrowth = $yesterdayRevenue > 0 
                ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 2) 
                : 0;

            return [
                'total_users' => User::count(),
                'active_users_today' => User::where('updated_at', '>=', $today)->count(),
                'total_orders' => Order::count(),
                'orders_today' => $todayOrders,
                'order_growth_percent' => $orderGrowth,
                'total_revenue' => Order::where('status', 'completed')->sum('estimated_fare'),
                'revenue_today' => $todayRevenue,
                'revenue_growth_percent' => $revenueGrowth,
                'active_drivers' => DriverProfile::where('status', 'active')->count(),
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get order metrics
     */
    public function getOrderMetrics(): array
    {
        return Cache::remember('metrics:orders', self::CACHE_DURATION, function () {
            $today = now()->startOfDay();

            // Orders by status
            $ordersByStatus = Order::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Orders by service
            $ordersByService = Order::join('services', 'orders.service_id', '=', 'services.id')
                ->selectRaw('services.name, COUNT(*) as count')
                ->groupBy('services.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->pluck('count', 'name')
                ->toArray();

            // Hourly order distribution (today)
            $hourlyDistribution = Order::whereDate('created_at', $today)
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->pluck('count', 'hour')
                ->toArray();

            // Average order value
            $avgOrderValue = Order::where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(30))
                ->avg('estimated_fare') ?? 0;

            // Order completion rate
            $totalCompleted = Order::where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            $totalOrders = Order::where('created_at', '>=', now()->subDays(30))->count();
            $completionRate = $totalOrders > 0 ? round(($totalCompleted / $totalOrders) * 100, 2) : 0;

            // Average delivery time
            $avgDeliveryTime = Order::where('status', 'completed')
                ->whereNotNull('completed_at')
                ->whereNotNull('created_at')
                ->where('created_at', '>=', now()->subDays(30))
                ->get()
                ->avg(function ($order) {
                    return $order->created_at->diffInMinutes($order->completed_at);
                }) ?? 0;

            return [
                'orders_by_status' => $ordersByStatus,
                'orders_by_service' => $ordersByService,
                'hourly_distribution' => $hourlyDistribution,
                'average_order_value' => round($avgOrderValue, 2),
                'completion_rate_percent' => $completionRate,
                'average_delivery_time_minutes' => round($avgDeliveryTime, 2),
                'orders_last_30_days' => $totalOrders,
                'completed_last_30_days' => $totalCompleted,
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get revenue metrics
     */
    public function getRevenueMetrics(): array
    {
        return Cache::remember('metrics:revenue', self::CACHE_DURATION, function () {
            // Daily revenue (last 30 days)
            $dailyRevenue = Order::where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, SUM(estimated_fare) as revenue, COUNT(*) as orders')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn($item) => [
                    'date' => $item->date,
                    'revenue' => (float) $item->revenue,
                    'orders' => (int) $item->orders,
                ]);

            // Revenue by payment method
            $revenueByPayment = Order::where('status', 'completed')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('payment_method, SUM(estimated_fare) as revenue, COUNT(*) as count')
                ->groupBy('payment_method')
                ->get()
                ->pluck('revenue', 'payment_method')
                ->toArray();

            // Total revenue metrics
            $totalRevenue = $dailyRevenue->sum('revenue');
            $avgDailyRevenue = $dailyRevenue->count() > 0 ? $totalRevenue / $dailyRevenue->count() : 0;

            // Revenue growth (compare this week vs last week)
            $thisWeekRevenue = Order::where('status', 'completed')
                ->whereBetween('created_at', [now()->startOfWeek(), now()])
                ->sum('estimated_fare');
            $lastWeekRevenue = Order::where('status', 'completed')
                ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->startOfWeek()])
                ->sum('estimated_fare');
            $revenueGrowth = $lastWeekRevenue > 0 
                ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 2) 
                : 0;

            return [
                'daily_revenue' => $dailyRevenue,
                'total_revenue_last_30_days' => round($totalRevenue, 2),
                'average_daily_revenue' => round($avgDailyRevenue, 2),
                'revenue_by_payment_method' => $revenueByPayment,
                'this_week_revenue' => round($thisWeekRevenue, 2),
                'last_week_revenue' => round($lastWeekRevenue, 2),
                'revenue_growth_percent' => $revenueGrowth,
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get user metrics
     */
    public function getUserMetrics(): array
    {
        return Cache::remember('metrics:users', self::CACHE_DURATION, function () {
            // User growth (last 30 days)
            $userGrowth = User::where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
                ->toArray();

            // Users by role
            $usersByRole = User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray();

            // Active users (last 7 days)
            $activeUsers7d = User::where('updated_at', '>=', now()->subDays(7))->count();
            $activeUsers30d = User::where('updated_at', '>=', now()->subDays(30))->count();

            // User retention rate
            $totalUsers = User::count();
            $retentionRate = $totalUsers > 0 ? round(($activeUsers30d / $totalUsers) * 100, 2) : 0;

            // New users today
            $newUsersToday = User::whereDate('created_at', today())->count();
            $newUsersYesterday = User::whereDate('created_at', today()->subDay())->count();
            $userGrowthDaily = $newUsersYesterday > 0 
                ? round((($newUsersToday - $newUsersYesterday) / $newUsersYesterday) * 100, 2) 
                : 0;

            return [
                'total_users' => $totalUsers,
                'users_by_role' => $usersByRole,
                'active_users_7d' => $activeUsers7d,
                'active_users_30d' => $activeUsers30d,
                'retention_rate_percent' => $retentionRate,
                'new_users_today' => $newUsersToday,
                'new_users_yesterday' => $newUsersYesterday,
                'daily_growth_percent' => $userGrowthDaily,
                'user_growth_30d' => $userGrowth,
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get driver metrics
     */
    public function getDriverMetrics(): array
    {
        return Cache::remember('metrics:drivers', self::CACHE_DURATION, function () {
            // Drivers by status
            $driversByStatus = DriverProfile::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Average driver rating
            $avgRating = DriverProfile::where('status', 'active')->avg('rating') ?? 0;

            // Top performing drivers
            $topDrivers = DriverProfile::with(['user:id,name'])
                ->where('status', 'active')
                ->where('total_trips', '>', 0)
                ->orderByDesc('rating')
                ->limit(10)
                ->get()
                ->map(fn($driver) => [
                    'id' => $driver->id,
                    'name' => $driver->user->name ?? 'Unknown',
                    'rating' => $driver->rating,
                    'total_trips' => $driver->total_trips,
                    'completion_rate' => $driver->total_trips > 0 
                        ? round(($driver->total_trips / max(1, $driver->total_trips)) * 100, 2) 
                        : 0,
                ]);

            // Active drivers today (updated location in last hour)
            $activeDriversToday = DriverProfile::where('status', 'active')
                ->where('updated_at', '>=', now()->subHour())
                ->count();

            // Driver utilization (active drivers / total active drivers)
            $totalActiveDrivers = DriverProfile::where('status', 'active')->count();
            $utilizationRate = $totalActiveDrivers > 0 
                ? round(($activeDriversToday / $totalActiveDrivers) * 100, 2) 
                : 0;

            return [
                'drivers_by_status' => $driversByStatus,
                'average_rating' => round($avgRating, 2),
                'top_drivers' => $topDrivers,
                'active_drivers_today' => $activeDriversToday,
                'total_active_drivers' => $totalActiveDrivers,
                'utilization_rate_percent' => $utilizationRate,
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get customer satisfaction metrics
     */
    public function getSatisfactionMetrics(): array
    {
        return Cache::remember('metrics:satisfaction', self::CACHE_DURATION, function () {
            // Overall rating statistics
            $avgRating = RatingReview::where('status', 'approved')->avg('overall_rating') ?? 0;
            $totalReviews = RatingReview::where('status', 'approved')->count();

            // Rating distribution
            $ratingDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $ratingDistribution[$i] = RatingReview::where('status', 'approved')
                    ->where('overall_rating', $i)
                    ->count();
            }

            // Reviews over time (last 30 days)
            $reviewsOverTime = RatingReview::where('status', 'approved')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, AVG(overall_rating) as avg_rating, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn($item) => [
                    'date' => $item->date,
                    'avg_rating' => round($item->avg_rating, 2),
                    'count' => (int) $item->count,
                ]);

            // Satisfaction rate (4-5 star reviews)
            $positiveReviews = RatingReview::where('status', 'approved')
                ->where('overall_rating', '>=', 4)
                ->count();
            $satisfactionRate = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100, 2) : 0;

            return [
                'average_rating' => round($avgRating, 2),
                'total_reviews' => $totalReviews,
                'rating_distribution' => $ratingDistribution,
                'satisfaction_rate_percent' => $satisfactionRate,
                'reviews_over_time' => $reviewsOverTime,
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get support metrics
     */
    public function getSupportMetrics(): array
    {
        return Cache::remember('metrics:support', self::CACHE_DURATION, function () {
            // Tickets by status
            $ticketsByStatus = \App\Models\SupportTicket::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Average response time
            $avgResponseTime = \App\Models\SupportTicket::whereNotNull('first_response_at')
                ->get()
                ->avg('response_time') ?? 0;

            // Average resolution time
            $avgResolutionTime = \App\Models\SupportTicket::whereNotNull('resolved_at')
                ->get()
                ->avg('resolution_time') ?? 0;

            // Satisfaction rating
            $avgSatisfaction = \App\Models\SupportTicket::whereNotNull('satisfaction_rating')
                ->avg('satisfaction_rating') ?? 0;

            // Open tickets
            $openTickets = \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
            $overdueTickets = \App\Models\SupportTicket::overdue()->count();

            return [
                'tickets_by_status' => $ticketsByStatus,
                'open_tickets' => $openTickets,
                'overdue_tickets' => $overdueTickets,
                'average_response_time_hours' => round($avgResponseTime, 2),
                'average_resolution_time_hours' => round($avgResolutionTime, 2),
                'average_satisfaction_rating' => round($avgSatisfaction, 2),
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get system performance metrics
     */
    public function getSystemPerformanceMetrics(): array
    {
        return Cache::remember('metrics:system', self::CACHE_DURATION, function () {
            $apmService = app(ApmService::class);

            return [
                'apm' => $apmService->getPerformanceSummary(),
                'queue_health' => app(QueueService::class)->getQueueHealth(),
                'database_connections' => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_CONNECTION_STATUS),
                'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Clear all cached metrics
     */
    public function clearCachedMetrics(): void
    {
        Cache::forget('metrics:overview');
        Cache::forget('metrics:orders');
        Cache::forget('metrics:revenue');
        Cache::forget('metrics:users');
        Cache::forget('metrics:drivers');
        Cache::forget('metrics:satisfaction');
        Cache::forget('metrics:support');
        Cache::forget('metrics:system');
    }
}
