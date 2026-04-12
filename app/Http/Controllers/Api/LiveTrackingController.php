<?php

namespace App\Http\Controllers\Api;

use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\OrderTrackingEvent;
use App\Events\OrderDriverAssigned;
use App\Services\RealtimeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiveTrackingController extends Controller
{
    public function __construct(
        private RealtimeService $realtimeService
    ) {}
    /**
     * Update driver's current location.
     */
    public function updateDriverLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'required|integer|exists:driver_profiles,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'altitude' => 'nullable|numeric',
            'order_id' => 'nullable|integer|exists:orders,id',
            'status' => 'nullable|in:online,offline,on_trip',
        ]);

        $user = $request->user();

        // Verify driver owns this profile or is admin
        $driverProfile = DriverProfile::find($validated['driver_id']);
        
        if (!$driverProfile) {
            return $this->error('Driver profile not found.', 404);
        }

        if ((int) $driverProfile->user_id !== (int) $user->id && !$user->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        // Create location record
        $location = DriverLocation::create([
            'driver_id' => $driverProfile->user_id,
            'order_id' => $validated['order_id'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'altitude' => $validated['altitude'] ?? null,
            'status' => $validated['status'] ?? 'online',
            'recorded_at' => now(),
            'metadata' => [
                'user_agent' => $request->userAgent(),
            ],
        ]);

        // Update driver profile's last location
        $driverProfile->update([
            'last_latitude' => $validated['latitude'],
            'last_longitude' => $validated['longitude'],
        ]);

        // Broadcast location update via RealtimeService
        if ($validated['order_id']) {
            $this->realtimeService->broadcastDriverLocationUpdate(
                $location,
                $driverProfile->user,
                $validated['order_id']
            );
        } else {
            // Broadcast to nearby drivers channel
            broadcast(new \App\Events\DriverLocationUpdated(
                $location,
                $driverProfile->user,
                null
            ));
        }

        return $this->created($location, 'Driver location updated successfully');
    }

    /**
     * Get driver's current location.
     */
    public function getDriverLocation($driverId): JsonResponse
    {
        $driverProfile = DriverProfile::find($driverId);

        if (!$driverProfile) {
            return $this->error('Driver profile not found.', 404);
        }

        // Get most recent location
        $location = DriverLocation::forDriver($driverProfile->user_id)
            ->orderByDesc('recorded_at')
            ->first();

        if (!$location) {
            return $this->success([
                'driver_id' => $driverId,
                'latitude' => $driverProfile->last_latitude,
                'longitude' => $driverProfile->last_longitude,
                'has_live_location' => false,
            ], 'Driver location retrieved successfully (last known)');
        }

        return $this->success([
            'driver_id' => $driverId,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy' => $location->accuracy,
            'speed' => $location->speed,
            'heading' => $location->heading,
            'status' => $location->status,
            'recorded_at' => $location->recorded_at->toISOString(),
            'is_recent' => $location->isRecent(5),
            'has_live_location' => true,
        ], 'Driver location retrieved successfully');
    }

    /**
     * Get driver's location history.
     */
    public function getDriverLocationHistory(Request $request, $driverId): JsonResponse
    {
        $validated = $request->validate([
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $driverProfile = DriverProfile::find($driverId);

        if (!$driverProfile) {
            return $this->error('Driver profile not found.', 404);
        }

        $query = DriverLocation::forDriver($driverProfile->user_id)
            ->orderByDesc('recorded_at');

        if (!empty($validated['start_time'])) {
            $query->where('recorded_at', '>=', $validated['start_time']);
        }

        if (!empty($validated['end_time'])) {
            $query->where('recorded_at', '<=', $validated['end_time']);
        }

        $limit = (int) ($validated['limit'] ?? 50);
        $locations = $query->limit($limit)->get();

        return $this->success($locations, 'Driver location history retrieved successfully');
    }

    /**
     * Get live order tracking information.
     */
    public function getOrderTracking($orderId): JsonResponse
    {
        $order = Order::with([
            'driverProfile.user:id,name,phone_number',
            'customer:id,name,phone_number',
            'service:id,name,code',
        ])->find($orderId);

        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        $trackingData = [
            'order_id' => $order->id,
            'status' => $order->status,
            'customer' => [
                'name' => $order->customer->name ?? null,
                'phone' => $order->customer->phone_number ?? null,
            ],
            'pickup' => [
                'address' => $order->pickup_address,
                'latitude' => (float) $order->pickup_lat,
                'longitude' => (float) $order->pickup_lng,
            ],
            'dropoff' => [
                'address' => $order->dropoff_address,
                'latitude' => (float) $order->dropoff_lat,
                'longitude' => (float) $order->dropoff_lng,
            ],
            'driver' => null,
            'driver_location' => null,
            'eta_minutes' => null,
            'distance_to_destination_km' => null,
            'tracking_events' => [],
        ];

        // Add driver info if assigned
        if ($order->driverProfile && $order->driverProfile->user) {
            $driver = $order->driverProfile->user;
            $trackingData['driver'] = [
                'id' => $order->driverProfile->id,
                'name' => $driver->name,
                'phone' => $driver->phone_number,
                'rating' => $order->driverProfile->rating,
            ];

            // Get driver's current location
            $driverLocation = DriverLocation::forDriver($driver->id)
                ->recent(5)
                ->orderByDesc('recorded_at')
                ->first();

            if ($driverLocation) {
                $trackingData['driver_location'] = [
                    'latitude' => (float) $driverLocation->latitude,
                    'longitude' => (float) $driverLocation->longitude,
                    'recorded_at' => $driverLocation->recorded_at->toISOString(),
                ];

                // Calculate distance to destination
                if ($order->status === 'picked_up' || $order->status === 'accepted') {
                    $distanceToDest = $driverLocation->distanceTo(
                        (float) $order->dropoff_lat,
                        (float) $order->dropoff_lng
                    );
                    $trackingData['distance_to_destination_km'] = round($distanceToDest, 2);
                    
                    // Estimate ETA (assuming average speed of 30 km/h in city)
                    $avgSpeed = 30;
                    $etaMinutes = ($distanceToDest / $avgSpeed) * 60;
                    $trackingData['eta_minutes'] = round($etaMinutes);
                }
            }
        }

        // Get tracking events
        $trackingEvents = OrderTrackingEvent::where('order_id', $orderId)
            ->orderBy('created_at')
            ->get();

        $trackingData['tracking_events'] = $trackingEvents->map(function ($event) {
            return [
                'id' => $event->id,
                'event' => $event->event_type,
                'description' => $event->description,
                'latitude' => $event->latitude ? (float) $event->latitude : null,
                'longitude' => $event->longitude ? (float) $event->longitude : null,
                'metadata' => $event->metadata,
                'timestamp' => $event->created_at->toISOString(),
            ];
        });

        return $this->success($trackingData, 'Order tracking retrieved successfully');
    }

    /**
     * Get nearby drivers.
     */
    public function getNearbyDrivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:1|max:50',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $lat = $validated['latitude'];
        $lng = $validated['longitude'];
        $radius = $validated['radius_km'] ?? 5;
        $limit = (int) ($validated['limit'] ?? 10);

        // Find active drivers nearby
        $drivers = DriverLocation::nearby($lat, $lng, $radius)
            ->recent(5)
            ->where('status', 'online')
            ->with(['driver:id,name,phone_number', 'driver.driverProfile'])
            ->get()
            ->sortBy(function ($location) use ($lat, $lng) {
                return $location->distanceTo($lat, $lng);
            })
            ->take($limit)
            ->map(function ($location) use ($lat, $lng) {
                $driver = $location->driver;
                $distance = $location->distanceTo($lat, $lng);

                return [
                    'driver_id' => $location->driver_id,
                    'driver_profile_id' => $driver->driverProfile->id ?? null,
                    'name' => $driver->name,
                    'phone' => $driver->phone_number,
                    'rating' => $driver->driverProfile->rating ?? 0,
                    'total_trips' => $driver->driverProfile->total_trips ?? 0,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'distance_km' => round($distance, 2),
                    'status' => $location->status,
                    'last_seen' => $location->recorded_at->toISOString(),
                ];
            })
            ->values();

        return $this->success($drivers, 'Nearby drivers retrieved successfully');
    }

    /**
     * Get tracking statistics.
     */
    public function getTrackingStats(): JsonResponse
    {
        $activeDrivers = DriverLocation::recent(5)
            ->where('status', 'online')
            ->distinct('driver_id')
            ->count('driver_id');

        $totalLocationsToday = DriverLocation::whereDate('recorded_at', today())->count();
        
        $activeOrders = Order::whereIn('status', ['accepted', 'picked_up', 'searching_driver'])
            ->count();

        $lastUpdate = DriverLocation::orderByDesc('recorded_at')->first();

        return $this->success([
            'active_drivers' => $activeDrivers,
            'active_orders' => $activeOrders,
            'total_locations_today' => $totalLocationsToday,
            'last_update' => $lastUpdate ? $lastUpdate->recorded_at->toISOString() : null,
        ], 'Tracking stats retrieved successfully');
    }
}
