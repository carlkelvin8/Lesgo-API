<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Models\AnalyticsEvent;
use App\Models\DailyMetric;
use App\Models\DriverPerformanceMetric;
use App\Models\CustomerBehaviorMetric;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    /**
     * Get comprehensive dashboard analytics
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'period' => 'nullable|in:today,week,month,quarter,year,custom',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to analytics dashboard',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        $cacheKey = "analytics:dashboard:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            return $this->analyticsService->getDashboardData($startDate, $endDate);
        });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'days' => $startDate->diffInDays($endDate) + 1,
                ],
                'analytics' => $data,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get revenue analytics
     */
    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'revenue_type' => 'nullable|in:gross,net,commission,driver_earnings',
            'service_id' => 'nullable|exists:services,id',
            'partner_id' => 'nullable|exists:partners,id',
            'group_by' => 'nullable|in:day,week,month,service,partner',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin() && !$user->isPartnerAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to revenue analytics',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        $filters = array_filter([
            'revenue_type' => $request->revenue_type,
            'service_id' => $request->service_id,
            'partner_id' => $request->partner_id,
        ]);

        $analytics = $this->analyticsService->getRevenueAnalytics($startDate, $endDate, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Revenue analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'filters' => $filters,
                'analytics' => $analytics,
            ],
        ]);
    }

    /**
     * Get driver performance analytics
     */
    public function driverPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'driver_id' => 'nullable|exists:users,id',
            'min_orders' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|in:performance_score,completion_rate,revenue,rating',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin() && !$user->isPartnerAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to driver analytics',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        $filters = array_filter([
            'driver_id' => $request->driver_id,
            'min_orders' => $request->min_orders,
        ]);

        $analytics = $this->analyticsService->getDriverPerformanceAnalytics($startDate, $endDate, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Driver performance analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'filters' => $filters,
                'analytics' => $analytics,
            ],
        ]);
    }

    /**
     * Get customer behavior analytics
     */
    public function customerBehavior(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'customer_id' => 'nullable|exists:users,id',
            'segment' => 'nullable|in:vip,high_value,regular,occasional,inactive',
            'churn_risk' => 'nullable|in:very_high,high,medium,low,very_low',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to customer analytics',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        $filters = array_filter([
            'customer_id' => $request->customer_id,
            'segment' => $request->segment,
            'churn_risk' => $request->churn_risk,
        ]);

        $analytics = $this->analyticsService->getCustomerBehaviorAnalytics($startDate, $endDate, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Customer behavior analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'filters' => $filters,
                'analytics' => $analytics,
            ],
        ]);
    }

    /**
     * Get service demand analytics
     */
    public function serviceDemand(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'service_id' => 'nullable|exists:services,id',
            'include_forecast' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin() && !$user->isPartnerAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to service demand analytics',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        $filters = array_filter([
            'service_id' => $request->service_id,
        ]);

        $analytics = $this->analyticsService->getServiceDemandAnalytics($startDate, $endDate, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Service demand analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'filters' => $filters,
                'analytics' => $analytics,
            ],
        ]);
    }

    /**
     * Get geofence effectiveness analytics
     */
    public function geofenceAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'geofence_id' => 'nullable|exists:geofences,id',
            'geofence_type' => 'nullable|in:delivery_zone,service_area,restricted_area,pickup_zone,partner_location',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to geofence analytics',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        $filters = array_filter([
            'geofence_id' => $request->geofence_id,
            'geofence_type' => $request->geofence_type,
        ]);

        $analytics = $this->analyticsService->getGeofenceAnalytics($startDate, $endDate, $filters);

        return response()->json([
            'success' => true,
            'message' => 'Geofence analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'filters' => $filters,
                'analytics' => $analytics,
            ],
        ]);
    }

    /**
     * Get predictive analytics
     */
    public function predictions(Request $request): JsonResponse
    {
        $request->validate([
            'prediction_type' => 'nullable|in:demand,revenue,churn,performance',
            'forecast_days' => 'nullable|integer|min:1|max:365',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to predictive analytics',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $startDate = now()->subDays(30);
        $endDate = now();

        $predictions = $this->analyticsService->getPredictiveInsights($startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Predictive analytics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'predictions' => $predictions,
                'generated_at' => now()->toISOString(),
                'model_accuracy' => $this->getModelAccuracy(),
            ],
        ]);
    }

    /**
     * Track custom analytics event
     */
    public function trackEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|max:100',
            'event_category' => 'required|string|max:50',
            'event_action' => 'required|string|max:50',
            'event_label' => 'nullable|string|max:100',
            'event_value' => 'nullable|numeric|min:0',
            'properties' => 'nullable|array',
            'context' => 'nullable|array',
        ]);

        $user = Auth::user();

        $event = $this->analyticsService->trackEvent(
            $request->event_type,
            $request->event_category,
            $request->event_action,
            $user,
            $request->event_label,
            $request->event_value,
            $request->properties ?? [],
            array_merge($request->context ?? [], [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Analytics event tracked successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'event_id' => $event->id,
                'tracked_at' => $event->event_time->toISOString(),
            ],
        ]);
    }

    /**
     * Get analytics events
     */
    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'event_type' => 'nullable|string',
            'event_category' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to analytics events',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        $query = AnalyticsEvent::whereBetween('event_time', [$startDate, $endDate])
            ->with('user:id,name,email,role')
            ->orderBy('event_time', 'desc');

        if ($request->event_type) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->event_category) {
            $query->where('event_category', $request->event_category);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $events = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Analytics events retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'events' => $events->items(),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Export analytics data
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'export_type' => 'required|in:dashboard,revenue,drivers,customers,services,geofences',
            'format' => 'nullable|in:json,csv,excel',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to analytics export',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        [$startDate, $endDate] = $this->getDateRange($request);

        // Generate export data based on type
        $data = match ($request->export_type) {
            'dashboard' => $this->analyticsService->getDashboardData($startDate, $endDate),
            'revenue' => $this->analyticsService->getRevenueAnalytics($startDate, $endDate),
            'drivers' => $this->analyticsService->getDriverPerformanceAnalytics($startDate, $endDate),
            'customers' => $this->analyticsService->getCustomerBehaviorAnalytics($startDate, $endDate),
            'services' => $this->analyticsService->getServiceDemandAnalytics($startDate, $endDate),
            'geofences' => $this->analyticsService->getGeofenceAnalytics($startDate, $endDate),
        };

        // For now, return JSON. In production, you might want to generate actual files
        return response()->json([
            'success' => true,
            'message' => 'Analytics data exported successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'export_type' => $request->export_type,
                'format' => $request->get('format', 'json'),
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'exported_data' => $data,
                'exported_at' => now()->toISOString(),
            ],
        ]);
    }

    // Private helper methods

    private function getDateRange(Request $request): array
    {
        $period = $request->get('period', 'week');

        if ($period === 'custom' && $request->start_date && $request->end_date) {
            return [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
        };
    }

    private function getModelAccuracy(): array
    {
        // Return mock accuracy data for now
        return [
            'demand_forecast' => 0.85,
            'revenue_forecast' => 0.78,
            'churn_prediction' => 0.82,
            'performance_prediction' => 0.79,
        ];
    }
}