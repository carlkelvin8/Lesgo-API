<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\User;
use App\Models\DriverProfile;
use App\Models\AnalyticsEvent;
use App\Models\DailyReport;
use App\Models\RevenueAnalytics;
use App\Models\DriverPerformanceMetric;
use App\Models\CustomerBehaviorMetric;
use App\Models\ServiceDemandMetric;
use App\Models\GeofenceAnalytics;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get analytics dashboard overview.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Only admins and partner admins can access full dashboard
        if ($user->isCustomer() || $user->isDriver()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'date_range' => 'nullable|in:today,week,month,year,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Determine date range
        $dateRange = $this->getDateRange($validated);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        $stats = [
            'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_revenue' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['completed'])
                ->sum('actual_fare') ?: Order::whereBetween('created_at', [$startDate, $endDate])
                    ->whereIn('status', ['completed'])
                    ->sum('estimated_fare'),
            'active_drivers' => DriverProfile::whereHas('user', function ($q) {
                $q->where('role', 'driver');
            })->where('status', 'active')->count(),
            'total_customers' => User::where('role', 'customer')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'completed_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->count(),
            'cancelled_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'cancelled')
                ->count(),
            'average_order_value' => 0,
            'customer_satisfaction' => 0,
        ];

        // Calculate average order value
        if ($stats['completed_orders'] > 0) {
            $stats['average_order_value'] = round($stats['total_revenue'] / $stats['completed_orders'], 2);
        }

        // Get customer satisfaction from ratings
        $avgRating = \App\Models\RatingReview::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'approved')
            ->avg('overall_rating');
        
        $stats['customer_satisfaction'] = $avgRating ? round($avgRating, 2) : 0;

        return $this->success($stats, 'Analytics dashboard retrieved successfully');
    }

    /**
     * Get revenue analytics data.
     */
    public function revenue(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->isCustomer() || $user->isDriver()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = $validated['period'] ?? 'daily';
        $dateRange = $this->getDateRange($validated);

        $query = Order::where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        $revenueData = [];

        if ($period === 'daily') {
            $revenueData = $query->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(COALESCE(actual_fare, estimated_fare)) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'orders' => (int) $item->orders,
                        'revenue' => (float) $item->revenue,
                    ];
                });
        } elseif ($period === 'weekly') {
            $revenueData = $query->selectRaw('YEARWEEK(created_at) as week, COUNT(*) as orders, SUM(COALESCE(actual_fare, estimated_fare)) as revenue')
                ->groupBy('week')
                ->orderBy('week')
                ->get()
                ->map(function ($item) {
                    return [
                        'week' => $item->week,
                        'orders' => (int) $item->orders,
                        'revenue' => (float) $item->revenue,
                    ];
                });
        } else {
            $revenueData = $query->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as orders, SUM(COALESCE(actual_fare, estimated_fare)) as revenue')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'year' => (int) $item->year,
                        'month' => (int) $item->month,
                        'orders' => (int) $item->orders,
                        'revenue' => (float) $item->revenue,
                    ];
                });
        }

        $totalRevenue = $revenueData->sum('revenue');
        $totalOrders = $revenueData->sum('orders');

        return $this->success([
            'period' => $period,
            'data' => $revenueData,
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_per_' . ($period === 'daily' ? 'day' : ($period === 'weekly' ? 'week' : 'month')) => $totalOrders > 0 ? round($totalRevenue / count($revenueData), 2) : 0,
        ], 'Revenue analytics retrieved successfully');
    }

    /**
     * Get driver performance analytics.
     */
    public function driverPerformance(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->isCustomer()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'driver_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DriverProfile::with(['user:id,name,email,phone_number'])
            ->where('status', 'active');

        if (!empty($validated['driver_id'])) {
            $query->where('id', $validated['driver_id']);
        }

        $limit = (int) ($validated['limit'] ?? 20);
        $drivers = $query->orderByDesc('rating')
            ->limit($limit)
            ->get()
            ->map(function ($driver) {
                $completedOrders = Order::where('driver_id', $driver->id)
                    ->where('status', 'completed')
                    ->count();
                
                $totalOrders = Order::where('driver_id', $driver->id)->count();
                $completionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;

                $totalEarnings = Order::where('driver_id', $driver->id)
                    ->where('status', 'completed')
                    ->sum('actual_fare') ?: 0;

                return [
                    'id' => $driver->id,
                    'user_id' => $driver->user_id,
                    'name' => $driver->user->name ?? 'Unknown',
                    'email' => $driver->user->email ?? '',
                    'rating' => $driver->rating,
                    'total_orders' => $totalOrders,
                    'completed_orders' => $completedOrders,
                    'completion_rate' => $completionRate,
                    'total_earnings' => $totalEarnings,
                    'status' => $driver->status,
                ];
            });

        return $this->success($drivers, 'Driver performance analytics retrieved successfully');
    }

    /**
     * Get customer behavior analytics.
     */
    public function customerBehavior(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin() && !$user->isPartnerAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:week,month,year',
        ]);

        $period = $validated['period'] ?? 'month';
        $days = $period === 'week' ? 7 : ($period === 'year' ? 365 : 30);
        $startDate = now()->subDays($days);

        $totalCustomers = User::where('role', 'customer')->count();
        $activeCustomers = User::where('role', 'customer')
            ->where('created_at', '>=', $startDate)
            ->count();

        // Top services
        $topServices = Order::join('services', 'orders.service_id', '=', 'services.id')
            ->selectRaw('services.id, services.name, COUNT(orders.id) as orders')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('orders')
            ->limit(10)
            ->get();

        // Average orders per customer
        $customersWithOrders = User::where('role', 'customer')
            ->has('orders')
            ->count();
        
        $totalOrders = Order::count();
        $avgOrdersPerCustomer = $customersWithOrders > 0 ? round($totalOrders / $customersWithOrders, 2) : 0;

        return $this->success([
            'period' => $period,
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'average_orders_per_customer' => $avgOrdersPerCustomer,
            'top_services' => $topServices,
        ], 'Customer behavior analytics retrieved successfully');
    }

    /**
     * Get service demand analytics.
     */
    public function serviceDemand(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin() && !$user->isPartnerAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'period' => 'nullable|in:week,month,year',
        ]);

        $period = $validated['period'] ?? 'month';
        $days = $period === 'week' ? 7 : ($period === 'year' ? 365 : 30);
        $startDate = now()->subDays($days);

        $services = \App\Models\Service::withCount(['orders' => function ($query) use ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }])
            ->withSum(['orders' => function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            }], 'estimated_fare')
            ->orderByDesc('orders_count')
            ->get()
            ->map(function ($service) {
                // Get peak hours for this service
                $peakHours = Order::where('service_id', $service->id)
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                    ->groupBy('hour')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->pluck('hour')
                    ->toArray();

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'code' => $service->code,
                    'total_orders' => $service->orders_count,
                    'total_revenue' => (float) ($service->orders_sum_estimated_fare ?? 0),
                    'peak_hours' => $peakHours,
                ];
            });

        return $this->success($services, 'Service demand analytics retrieved successfully');
    }

    /**
     * Get geofence effectiveness analytics.
     */
    public function geofenceAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $geofences = \App\Models\Geofence::withCount(['events' => function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        }])
            ->where('is_active', true)
            ->get()
            ->map(function ($geofence) {
                $ordersInGeofence = Order::where('pickup_lat', '>=', $geofence->latitude - ($geofence->radius_km / 111))
                    ->where('pickup_lat', '<=', $geofence->latitude + ($geofence->radius_km / 111))
                    ->where('pickup_lng', '>=', $geofence->longitude - ($geofence->radius_km / 111))
                    ->where('pickup_lng', '<=', $geofence->longitude + ($geofence->radius_km / 111))
                    ->count();

                $conversionRate = $geofence->events_count > 0 
                    ? round(($ordersInGeofence / $geofence->events_count) * 100, 2) 
                    : 0;

                return [
                    'id' => $geofence->id,
                    'name' => $geofence->name,
                    'type' => $geofence->type,
                    'total_entries' => $geofence->events_count,
                    'total_orders' => $ordersInGeofence,
                    'conversion_rate' => $conversionRate,
                ];
            });

        return $this->success($geofences, 'Geofence analytics retrieved successfully');
    }

    /**
     * Get predictive analytics/predictions.
     */
    public function predictions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin() && !$user->isPartnerAdmin()) {
            return $this->error('Forbidden', 403);
        }

        // Simple prediction based on historical data
        $next7Days = [];
        $avgOrders = Order::where('created_at', '>=', now()->subDays(30))->count() / 30;

        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i)->format('Y-m-d');
            // Simple prediction with some variance
            $predictedOrders = round($avgOrders * (0.9 + (rand(0, 20) / 100)));
            
            $next7Days[] = [
                'date' => $date,
                'predicted_orders' => max(0, $predictedOrders),
                'confidence' => 0.75 + (rand(0, 15) / 100),
            ];
        }

        return $this->success([
            'predicted_demand' => $next7Days,
            'based_on_days' => 30,
            'average_daily_orders' => round($avgOrders, 2),
        ], 'Predictions retrieved successfully');
    }

    /**
     * Get analytics events.
     */
    public function events(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'event_type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AnalyticsEvent::query();

        if (!empty($validated['event_type'])) {
            $query->where('event_type', $validated['event_type']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $events = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success($events, 'Analytics events retrieved successfully');
    }

    /**
     * Track an analytics event.
     */
    public function trackEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:100',
            'event_category' => 'nullable|string|max:100',
            'event_action' => 'nullable|string|max:100',
            'event_label' => 'nullable|string|max:255',
            'event_value' => 'nullable|numeric',
            'properties' => 'nullable|array',
        ]);

        $properties = $validated['properties'] ?? [];
        [$category, $action, $label] = $this->resolveTrackedEventMetadata(
            $validated['event_type'],
            $validated['event_category'] ?? null,
            $validated['event_action'] ?? null,
            $validated['event_label'] ?? null,
            $properties,
        );

        $event = AnalyticsEvent::track(
            $validated['event_type'],
            $category,
            $action,
            $request->user(),
            $label,
            isset($validated['event_value']) ? (float) $validated['event_value'] : null,
            $properties,
            [
                'device_type' => 'mobile',
                'platform' => $this->detectClientPlatform($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return $this->created($event, 'Event tracked successfully');
    }

    /**
     * Map mobile/client payloads to required analytics columns.
     *
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function resolveTrackedEventMetadata(
        string $eventType,
        ?string $category,
        ?string $action,
        ?string $label,
        array $properties,
    ): array {
        if ($eventType === 'screen_view') {
            return [
                $category ?? 'navigation',
                $action ?? 'view',
                $label ?? ($properties['screen'] ?? null),
            ];
        }

        return [
            $category ?? 'engagement',
            $action ?? $eventType,
            $label,
        ];
    }

    private function detectClientPlatform(Request $request): string
    {
        $userAgent = strtolower((string) $request->userAgent());

        if (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ios')) {
            return 'ios';
        }

        if (str_contains($userAgent, 'android')) {
            return 'android';
        }

        return 'mobile';
    }

    /**
     * Export analytics data.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:revenue,orders,drivers,customers',
            'format' => 'nullable|in:csv,json',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $dateRange = $this->getDateRange($validated);
        $format = $validated['format'] ?? 'json';

        // For now, return data directly. In production, generate file and return download URL
        $data = $this->getExportData($validated['type'], $dateRange);

        return $this->success([
            'format' => $format,
            'type' => $validated['type'],
            'data' => $data,
            'generated_at' => now()->toISOString(),
        ], 'Analytics exported successfully');
    }

    /**
     * Helper: Get date range from request.
     */
    private function getDateRange(array $validated): array
    {
        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            return [
                'start' => $validated['start_date'],
                'end' => $validated['end_date'],
            ];
        }

        $dateRange = $validated['date_range'] ?? 'week';
        
        return match ($dateRange) {
            'today' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'week' => ['start' => now()->subDays(7), 'end' => now()],
            'month' => ['start' => now()->subDays(30), 'end' => now()],
            'year' => ['start' => now()->subDays(365), 'end' => now()],
            default => ['start' => now()->subDays(7), 'end' => now()],
        };
    }

    /**
     * Helper: Get export data by type.
     */
    private function getExportData(string $type, array $dateRange): array
    {
        return match ($type) {
            'revenue' => [
                'total' => Order::whereBetween('created_at', $dateRange)
                    ->where('status', 'completed')
                    ->sum('estimated_fare'),
                'count' => Order::whereBetween('created_at', $dateRange)
                    ->where('status', 'completed')
                    ->count(),
            ],
            'orders' => [
                'total' => Order::whereBetween('created_at', $dateRange)->count(),
                'completed' => Order::whereBetween('created_at', $dateRange)->where('status', 'completed')->count(),
                'cancelled' => Order::whereBetween('created_at', $dateRange)->where('status', 'cancelled')->count(),
            ],
            'drivers' => [
                'total' => DriverProfile::count(),
                'active' => DriverProfile::where('status', 'active')->count(),
            ],
            'customers' => [
                'total' => User::where('role', 'customer')->count(),
                'new' => User::where('role', 'customer')
                    ->whereBetween('created_at', $dateRange)
                    ->count(),
            ],
            default => [],
        };
    }
}
