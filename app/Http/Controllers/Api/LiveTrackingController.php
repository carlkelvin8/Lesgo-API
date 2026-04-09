<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use App\Models\Order;
use App\Models\User;
use App\Services\RealtimeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LiveTrackingController extends Controller
{
    public function __construct(
        private RealtimeService $realtimeService
    ) {}

    /**
     * Update driver location
     */
    public function updateDriverLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'altitude' => 'nullable|numeric',
            'status' => 'nullable|in:online,offline,busy',
            'order_id' => 'nullable|exists:orders,id',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        // Only drivers can update location
        if (!$user->isDriver()) {
            return response()->json([
                'success' => false,
                'message' => 'Only drivers can update location',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        // Validate order ownership if provided
        $orderId = $request->get('order_id');
        if ($orderId) {
            $order = Order::where('id', $orderId)
                ->where('driver_id', $user->id)
                ->first();
                
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or not assigned to you',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                    'data' => null,
                ], 404);
            }
        }

        DB::beginTransaction();
        try {
            // Create location record
            $location = DriverLocation::create([
                'driver_id' => $user->id,
                'order_id' => $orderId,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy' => $request->get('accuracy'),
                'speed' => $request->get('speed'),
                'heading' => $request->get('heading'),
                'altitude' => $request->get('altitude'),
                'status' => $request->get('status', 'online'),
                'recorded_at' => now(),
                'metadata' => $request->get('metadata'),
            ]);

            // Broadcast location update
            $this->realtimeService->broadcastDriverLocationUpdate($location, $user, $orderId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Driver location updated successfully',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => [
                    'location' => $location->toArray(),
                    'broadcast_channels' => $orderId ? [
                        "driver.{$user->id}",
                        "order.{$orderId}",
                        "drivers.nearby"
                    ] : [
                        "driver.{$user->id}",
                        "drivers.nearby"
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update driver location',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get driver's current location
     */
    public function getDriverLocation(Request $request, int $driverId): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user can access this driver's location
        if ($user->id !== $driverId && !$user->isAdmin()) {
            // Check if user is customer with active order from this driver
            $hasActiveOrder = Order::where('customer_id', $user->id)
                ->where('driver_id', $driverId)
                ->whereIn('status', ['confirmed', 'in_progress'])
                ->exists();
                
            if (!$hasActiveOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to driver location',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                    'data' => null,
                ], 403);
            }
        }

        $location = DriverLocation::where('driver_id', $driverId)
            ->with('driver')
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Driver location not found',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver location retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'location' => $location->toArray(),
                'driver' => [
                    'id' => $location->driver->id,
                    'name' => $location->driver->name,
                    'phone_number' => $location->driver->phone_number,
                ],
                'is_recent' => $location->isRecent(5), // Within 5 minutes
            ],
        ]);
    }

    /**
     * Get live tracking for an order
     */
    public function getOrderTracking(Request $request, int $orderId): JsonResponse
    {
        $user = Auth::user();
        
        $order = Order::findOrFail($orderId);
        
        // Check if user can access this order's tracking
        if ($order->customer_id !== $user->id && $order->driver_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to order tracking',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        // Get driver location if driver is assigned
        $driverLocation = null;
        if ($order->driver_id) {
            $driverLocation = DriverLocation::where('driver_id', $order->driver_id)
                ->where('order_id', $orderId)
                ->orderBy('recorded_at', 'desc')
                ->first();
                
            // If no order-specific location, get latest driver location
            if (!$driverLocation) {
                $driverLocation = DriverLocation::where('driver_id', $order->driver_id)
                    ->orderBy('recorded_at', 'desc')
                    ->first();
            }
        }

        // Get location history for the order
        $locationHistory = DriverLocation::where('order_id', $orderId)
            ->orderBy('recorded_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Order tracking retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'customer_id' => $order->customer_id,
                    'driver_id' => $order->driver_id,
                    'pickup_address' => $order->pickupAddress?->toArray(),
                    'dropoff_address' => $order->dropoffAddress?->toArray(),
                    'scheduled_at' => $order->scheduled_at?->toISOString(),
                    'accepted_at' => $order->accepted_at?->toISOString(),
                    'picked_up_at' => $order->picked_up_at?->toISOString(),
                    'completed_at' => $order->completed_at?->toISOString(),
                ],
                'current_location' => $driverLocation?->toArray(),
                'location_history' => $locationHistory->toArray(),
                'driver' => $order->driver ? [
                    'id' => $order->driver->id,
                    'name' => $order->driver->name,
                    'phone_number' => $order->driver->phone_number,
                ] : null,
                'tracking_active' => $driverLocation && $driverLocation->isRecent(10),
            ],
        ]);
    }

    /**
     * Get nearby drivers
     */
    public function getNearbyDrivers(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radiusKm = $request->get('radius_km', 5);
        $limit = $request->get('limit', 20);

        // Get recent driver locations within radius
        $nearbyDrivers = DriverLocation::with('driver')
            ->nearby($latitude, $longitude, $radiusKm)
            ->online()
            ->recent(10) // Within 10 minutes
            ->limit($limit)
            ->get()
            ->map(function ($location) use ($latitude, $longitude) {
                return [
                    'driver_id' => $location->driver_id,
                    'driver' => [
                        'id' => $location->driver->id,
                        'name' => $location->driver->name,
                        'role' => $location->driver->role,
                    ],
                    'location' => [
                        'latitude' => (float) $location->latitude,
                        'longitude' => (float) $location->longitude,
                        'accuracy' => (float) $location->accuracy,
                        'speed' => (float) $location->speed,
                        'heading' => (float) $location->heading,
                        'status' => $location->status,
                        'recorded_at' => $location->recorded_at->toISOString(),
                    ],
                    'distance_km' => round($location->distanceTo($latitude, $longitude), 2),
                    'is_recent' => $location->isRecent(5),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Nearby drivers retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'drivers' => $nearbyDrivers,
                'search_params' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_km' => $radiusKm,
                    'limit' => $limit,
                ],
                'total_found' => $nearbyDrivers->count(),
            ],
        ]);
    }

    /**
     * Get driver location history
     */
    public function getDriverLocationHistory(Request $request, int $driverId): JsonResponse
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'order_id' => 'nullable|exists:orders,id',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $user = Auth::user();
        
        // Check if user can access this driver's location history
        if ($user->id !== $driverId && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to driver location history',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $query = DriverLocation::where('driver_id', $driverId)
            ->orderBy('recorded_at', 'desc');

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('recorded_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->where('recorded_at', '<=', $request->to_date);
        }

        // Filter by order
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        $locations = $query->limit($request->get('limit', 100))->get();

        return response()->json([
            'success' => true,
            'message' => 'Driver location history retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'locations' => $locations->toArray(),
                'driver_id' => $driverId,
                'total_records' => $locations->count(),
                'date_range' => [
                    'from' => $request->get('from_date'),
                    'to' => $request->get('to_date'),
                ],
            ],
        ]);
    }

    /**
     * Get tracking statistics
     */
    public function getTrackingStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to tracking statistics',
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'data' => null,
            ], 403);
        }

        $stats = [
            'active_drivers' => DriverLocation::online()->recent(10)->distinct('driver_id')->count(),
            'total_locations_today' => DriverLocation::whereDate('recorded_at', today())->count(),
            'locations_last_hour' => DriverLocation::where('recorded_at', '>=', now()->subHour())->count(),
            'drivers_with_orders' => DriverLocation::whereNotNull('order_id')->recent(60)->distinct('driver_id')->count(),
            'average_accuracy' => DriverLocation::recent(60)->avg('accuracy'),
            'status_distribution' => DriverLocation::recent(60)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Tracking statistics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $stats,
        ]);
    }
}