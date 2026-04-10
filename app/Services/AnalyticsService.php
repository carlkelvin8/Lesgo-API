<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\DailyMetric;
use App\Models\DriverPerformanceMetric;
use App\Models\CustomerBehaviorMetric;
use App\Models\ServiceDemandMetric;
use App\Models\GeofenceAnalytics;
use App\Models\RevenueAnalytics;
use App\Models\Order;
use App\Models\User;
use App\Models\Service;
use App\Models\Geofence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Track an analytics event
     */
    public function trackEvent(
        string $eventType,
        string $category,
        string $action,
        ?User $user = null,
        ?string $label = null,
        ?float $value = null,
        array $properties = [],
        array $context = []
    ): AnalyticsEvent {
        return AnalyticsEvent::track(
            $eventType,
            $category,
            $action,
            $user,
            $label,
            $value,
            $properties,
            $context
        );
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $query = RevenueAnalytics::whereBetween('date', [$startDate, $endDate]);

        if (isset($filters['revenue_type'])) {
            $query->where('revenue_type', $filters['revenue_type']);
        }

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        $analytics = $query->get();

        return [
            'total_revenue' => $analytics->sum('amount'),
            'average_daily_revenue' => $analytics->avg('amount'),
            'revenue_by_type' => $analytics->groupBy('revenue_type')
                ->map(fn($group) => $group->sum('amount')),
            'revenue_by_service' => $analytics->whereNotNull('service_id')
                ->groupBy('service_id')
                ->map(fn($group) => $group->sum('amount')),
            'daily_breakdown' => $analytics->groupBy('date')
                ->map(fn($group) => $group->sum('amount')),
            'growth_rate' => $this->calculateGrowthRate($analytics),
            'forecast' => $this->forecastRevenue($analytics, 30), // 30 days forecast
        ];
    }

    /**
     * Get driver performance analytics
     */
    public function getDriverPerformanceAnalytics(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $query = DriverPerformanceMetric::whereBetween('date', [$startDate, $endDate]);

        if (isset($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        $metrics = $query->with('driver')->get();

        $topPerformers = $metrics->sortByDesc(function ($metric) {
            return $metric->calculatePerformanceScore();
        })->take(10);

        return [
            'total_drivers' => $metrics->unique('driver_id')->count(),
            'active_drivers' => $metrics->where('total_orders', '>', 0)->unique('driver_id')->count(),
            'average_performance_score' => $metrics->avg(function ($metric) {
                return $metric->calculatePerformanceScore();
            }),
            'total_orders' => $metrics->sum('total_orders'),
            'total_completed_orders' => $metrics->sum('completed_orders'),
            'total_revenue' => $metrics->sum('total_revenue'),
            'average_completion_rate' => $metrics->avg('completion_rate'),
            'average_rating' => $metrics->where('average_rating', '>', 0)->avg('average_rating'),
            'top_performers' => $topPerformers->map(function ($metric) {
                return [
                    'driver' => $metric->driver->name,
                    'performance_score' => $metric->calculatePerformanceScore(),
                    'completion_rate' => $metric->completion_rate,
                    'average_rating' => $metric->average_rating,
                    'total_revenue' => $metric->total_revenue,
                ];
            }),
            'performance_distribution' => $this->getPerformanceDistribution($metrics),
        ];
    }

    /**
     * Get customer behavior analytics
     */
    public function getCustomerBehaviorAnalytics(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $query = CustomerBehaviorMetric::whereBetween('date', [$startDate, $endDate]);

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $metrics = $query->with('customer')->get();

        return [
            'total_customers' => $metrics->unique('customer_id')->count(),
            'active_customers' => $metrics->where('total_orders', '>', 0)->unique('customer_id')->count(),
            'total_clv' => $metrics->sum('customer_lifetime_value'),
            'average_clv' => $metrics->avg('customer_lifetime_value'),
            'average_order_value' => $metrics->avg('average_order_value'),
            'churn_risk_customers' => $metrics->where('churn_probability', '>=', 0.7)->count(),
            'customer_segments' => $this->getCustomerSegments($metrics),
            'engagement_levels' => $this->getEngagementLevels($metrics),
            'retention_analysis' => $this->getRetentionAnalysis($metrics),
            'preferred_services' => $this->getPreferredServices($metrics),
        ];
    }

    /**
     * Get service demand analytics
     */
    public function getServiceDemandAnalytics(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $query = ServiceDemandMetric::whereBetween('date', [$startDate, $endDate]);

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        $metrics = $query->with('service')->get();

        return [
            'total_requests' => $metrics->sum('total_requests'),
            'completed_requests' => $metrics->sum('completed_requests'),
            'completion_rate' => $metrics->sum('total_requests') > 0 ? 
                ($metrics->sum('completed_requests') / $metrics->sum('total_requests')) * 100 : 0,
            'demand_by_service' => $metrics->groupBy('service_id')
                ->map(fn($group) => [
                    'service_name' => $group->first()->service->name ?? 'Unknown',
                    'total_requests' => $group->sum('total_requests'),
                    'completion_rate' => $group->sum('total_requests') > 0 ? 
                        ($group->sum('completed_requests') / $group->sum('total_requests')) * 100 : 0,
                ]),
            'peak_hours' => $this->getPeakDemandHours($metrics),
            'demand_forecast' => $this->forecastDemand($metrics, 7), // 7 days forecast
            'seasonal_patterns' => $this->getSeasonalPatterns($metrics),
        ];
    }

    /**
     * Get geofence effectiveness analytics
     */
    public function getGeofenceAnalytics(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $query = GeofenceAnalytics::whereBetween('date', [$startDate, $endDate]);

        if (isset($filters['geofence_id'])) {
            $query->where('geofence_id', $filters['geofence_id']);
        }

        $analytics = $query->with('geofence')->get();

        return [
            'total_geofences' => $analytics->unique('geofence_id')->count(),
            'total_events' => $analytics->sum('total_entries') + $analytics->sum('total_exits'),
            'total_conversions' => $analytics->sum('orders_triggered'),
            'average_conversion_rate' => $analytics->avg('conversion_rate'),
            'total_revenue_generated' => $analytics->sum('revenue_generated'),
            'geofence_performance' => $analytics->groupBy('geofence_id')
                ->map(function ($group) {
                    $geofence = $group->first()->geofence;
                    return [
                        'name' => $geofence->name ?? 'Unknown',
                        'type' => $geofence->type ?? 'Unknown',
                        'total_entries' => $group->sum('total_entries'),
                        'conversion_rate' => $group->avg('conversion_rate'),
                        'revenue_generated' => $group->sum('revenue_generated'),
                        'effectiveness_score' => $this->calculateGeofenceEffectiveness($group),
                    ];
                }),
            'roi_analysis' => $this->calculateGeofenceROI($analytics),
        ];
    }

    /**
     * Generate comprehensive dashboard data
     */
    public function getDashboardData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'overview' => $this->getOverviewMetrics($startDate, $endDate),
            'revenue' => $this->getRevenueAnalytics($startDate, $endDate),
            'drivers' => $this->getDriverPerformanceAnalytics($startDate, $endDate),
            'customers' => $this->getCustomerBehaviorAnalytics($startDate, $endDate),
            'services' => $this->getServiceDemandAnalytics($startDate, $endDate),
            'geofences' => $this->getGeofenceAnalytics($startDate, $endDate),
            'trends' => $this->getTrendAnalysis($startDate, $endDate),
            'predictions' => $this->getPredictiveInsights($startDate, $endDate),
        ];
    }

    /**
     * Calculate daily metrics for all entities
     */
    public function calculateDailyMetrics(Carbon $date): void
    {
        Log::info('Calculating daily metrics', ['date' => $date->toDateString()]);

        try {
            DB::beginTransaction();

            // Calculate revenue metrics
            $this->calculateRevenueMetrics($date);

            // Calculate driver performance metrics
            $this->calculateDriverMetrics($date);

            // Calculate customer behavior metrics
            $this->calculateCustomerMetrics($date);

            // Calculate service demand metrics
            $this->calculateServiceDemandMetrics($date);

            // Calculate geofence analytics
            $this->calculateGeofenceMetrics($date);

            // Calculate overall daily metrics
            $this->calculateOverallMetrics($date);

            DB::commit();

            Log::info('Daily metrics calculated successfully', ['date' => $date->toDateString()]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to calculate daily metrics', [
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get predictive insights
     */
    public function getPredictiveInsights(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'demand_forecast' => $this->predictDemand($endDate->addDays(7)),
            'revenue_forecast' => $this->predictRevenue($endDate->addDays(30)),
            'churn_predictions' => $this->predictCustomerChurn(),
            'driver_performance_trends' => $this->predictDriverPerformance(),
            'seasonal_adjustments' => $this->getSeasonalAdjustments(),
        ];
    }

    // Private helper methods

    private function calculateGrowthRate(Collection $data): float
    {
        if ($data->count() < 2) {
            return 0;
        }

        $sorted = $data->sortBy('date');
        $first = $sorted->first()->amount;
        $last = $sorted->last()->amount;

        if ($first == 0) {
            return 0;
        }

        return (($last - $first) / $first) * 100;
    }

    private function forecastRevenue(Collection $data, int $days): array
    {
        // Simple linear regression forecast
        $values = $data->pluck('amount')->toArray();
        $trend = $this->calculateTrend($values);
        
        $forecast = [];
        $lastValue = end($values);
        
        for ($i = 1; $i <= $days; $i++) {
            $forecast[] = max(0, $lastValue + ($trend * $i));
        }

        return $forecast;
    }

    private function calculateTrend(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $n = count($values);
        $sumX = array_sum(range(1, $n));
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $values[$i];
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        return ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    }

    private function getPerformanceDistribution(Collection $metrics): array
    {
        $distribution = [
            'excellent' => 0,
            'very_good' => 0,
            'good' => 0,
            'average' => 0,
            'needs_improvement' => 0,
        ];

        foreach ($metrics as $metric) {
            $rating = $metric->getEfficiencyRating();
            $key = strtolower(str_replace(' ', '_', $rating));
            if (isset($distribution[$key])) {
                $distribution[$key]++;
            }
        }

        return $distribution;
    }

    private function getCustomerSegments(Collection $metrics): array
    {
        $segments = [];
        
        foreach ($metrics as $metric) {
            $segment = $metric->getCustomerSegment();
            $segments[$segment] = ($segments[$segment] ?? 0) + 1;
        }

        return $segments;
    }

    private function getEngagementLevels(Collection $metrics): array
    {
        $levels = [];
        
        foreach ($metrics as $metric) {
            $level = $metric->getEngagementLevel();
            $levels[$level] = ($levels[$level] ?? 0) + 1;
        }

        return $levels;
    }

    private function getRetentionAnalysis(Collection $metrics): array
    {
        return [
            'high_retention' => $metrics->where('churn_probability', '<', 0.3)->count(),
            'medium_retention' => $metrics->whereBetween('churn_probability', [0.3, 0.7])->count(),
            'low_retention' => $metrics->where('churn_probability', '>=', 0.7)->count(),
            'average_retention_score' => $metrics->avg(function ($metric) {
                return $metric->calculateRetentionScore();
            }),
        ];
    }

    private function getPreferredServices(Collection $metrics): array
    {
        $servicePreferences = [];
        
        foreach ($metrics as $metric) {
            if ($metric->preferred_services) {
                foreach ($metric->preferred_services as $serviceId) {
                    $servicePreferences[$serviceId] = ($servicePreferences[$serviceId] ?? 0) + 1;
                }
            }
        }

        return $servicePreferences;
    }

    private function getPeakDemandHours(Collection $metrics): array
    {
        return $metrics->groupBy('hour_of_day')
            ->map(fn($group) => $group->sum('total_requests'))
            ->sortDesc()
            ->take(5)
            ->toArray();
    }

    private function forecastDemand(Collection $metrics, int $days): array
    {
        // Implement demand forecasting logic
        return [];
    }

    private function getSeasonalPatterns(Collection $metrics): array
    {
        return $metrics->groupBy('day_of_week')
            ->map(fn($group) => $group->avg('total_requests'))
            ->toArray();
    }

    private function calculateGeofenceEffectiveness(Collection $group): float
    {
        $conversionRate = $group->avg('conversion_rate');
        $revenuePerEvent = $group->sum('revenue_generated') / max($group->sum('total_entries'), 1);
        $notificationCTR = $group->avg('notification_ctr');

        return ($conversionRate * 0.5) + ($revenuePerEvent * 0.3) + ($notificationCTR * 0.2);
    }

    private function calculateGeofenceROI(Collection $analytics): array
    {
        $totalRevenue = $analytics->sum('revenue_generated');
        $totalCost = $analytics->count() * 100; // Estimated cost per geofence

        return [
            'total_revenue' => $totalRevenue,
            'estimated_cost' => $totalCost,
            'roi' => $totalCost > 0 ? (($totalRevenue - $totalCost) / $totalCost) * 100 : 0,
        ];
    }

    private function getOverviewMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'completed_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')->count(),
            'total_revenue' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')->sum('actual_fare'),
            'active_drivers' => DriverPerformanceMetric::whereBetween('date', [$startDate, $endDate])
                ->where('total_orders', '>', 0)->distinct('driver_id')->count(),
            'active_customers' => CustomerBehaviorMetric::whereBetween('date', [$startDate, $endDate])
                ->where('total_orders', '>', 0)->distinct('customer_id')->count(),
        ];
    }

    private function getTrendAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        // Implement trend analysis logic
        return [
            'revenue_trend' => 'increasing',
            'order_trend' => 'stable',
            'customer_acquisition_trend' => 'increasing',
            'driver_performance_trend' => 'improving',
        ];
    }

    // Calculation methods for daily metrics
    private function calculateRevenueMetrics(Carbon $date): void
    {
        $orders = Order::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->get();

        $totalRevenue = $orders->sum('actual_fare');
        $platformFee = $orders->sum('platform_fee');
        $driverEarnings = $orders->sum('driver_share');
        $partnerEarnings = $orders->sum('partner_share');

        RevenueAnalytics::updateOrCreate(
            ['date' => $date, 'revenue_type' => 'gross', 'revenue_source' => 'orders'],
            ['amount' => $totalRevenue, 'transaction_count' => $orders->count()]
        );

        RevenueAnalytics::updateOrCreate(
            ['date' => $date, 'revenue_type' => 'platform_fee', 'revenue_source' => 'orders'],
            ['amount' => $platformFee, 'transaction_count' => $orders->count()]
        );
    }

    private function calculateDriverMetrics(Carbon $date): void
    {
        $drivers = User::where('role', 'driver')->get();

        foreach ($drivers as $driver) {
            DriverPerformanceMetric::calculateForDriver($driver, $date);
        }
    }

    private function calculateCustomerMetrics(Carbon $date): void
    {
        $customers = User::where('role', 'customer')->get();

        foreach ($customers as $customer) {
            CustomerBehaviorMetric::calculateForCustomer($customer, $date);
        }
    }

    private function calculateServiceDemandMetrics(Carbon $date): void
    {
        // Implement service demand calculation
    }

    private function calculateGeofenceMetrics(Carbon $date): void
    {
        // Implement geofence analytics calculation
    }

    private function calculateOverallMetrics(Carbon $date): void
    {
        // Calculate and store overall daily metrics
        $totalOrders = Order::whereDate('created_at', $date)->count();
        $completedOrders = Order::whereDate('created_at', $date)->where('status', 'completed')->count();
        $totalRevenue = Order::whereDate('created_at', $date)->where('status', 'completed')->sum('actual_fare');

        DailyMetric::record($date, 'orders', 'total_orders', $totalOrders);
        DailyMetric::record($date, 'orders', 'completed_orders', $completedOrders);
        DailyMetric::record($date, 'revenue', 'total_revenue', $totalRevenue);
    }

    // Prediction methods
    private function predictDemand(Carbon $date): array
    {
        // Implement demand prediction logic
        return [];
    }

    private function predictRevenue(Carbon $date): array
    {
        // Implement revenue prediction logic
        return [];
    }

    private function predictCustomerChurn(): array
    {
        // Implement churn prediction logic
        return [];
    }

    private function predictDriverPerformance(): array
    {
        // Implement driver performance prediction logic
        return [];
    }

    private function getSeasonalAdjustments(): array
    {
        // Implement seasonal adjustment logic
        return [];
    }
}