<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderTrackingController extends Controller
{
    /**
     * Get tracking events for a specific order.
     */
    public function trackOrder(Order $order): JsonResponse
    {
        // Check if user can view this order
        if ($order->user_id !== auth()->id() && $order->driver_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to track this order',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $events = $order->trackingEvents()
            ->visibleToCustomer()
            ->with(['user'])
            ->ordered('desc')
            ->get();

        // Get current order status and estimated delivery time
        $orderData = [
            'id' => $order->id,
            'status' => $order->status,
            'pickup_address' => $order->pickup_address,
            'delivery_address' => $order->delivery_address,
            'estimated_delivery_time' => $order->estimated_delivery_time,
            'driver' => $order->driver ? [
                'id' => $order->driver->id,
                'name' => $order->driver->name,
                'phone' => $order->driver->phone ?? null,
                'rating' => $order->driver->average_rating ?? null,
            ] : null,
            'service' => $order->service ? [
                'id' => $order->service->id,
                'name' => $order->service->name,
                'type' => $order->service->type,
            ] : null,
        ];

        // Get latest milestone event
        $latestMilestone = $events->where('is_milestone', true)->first();

        return response()->json([
            'success' => true,
            'message' => 'Order tracking retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => [
                'order' => $orderData,
                'current_status' => [
                    'status' => $order->status,
                    'status_display' => $this->getStatusDisplay($order->status),
                    'latest_milestone' => $latestMilestone,
                    'progress_percentage' => $this->getProgressPercentage($order->status),
                ],
                'tracking_events' => $events,
                'timeline' => $this->buildTimeline($events),
            ],
        ]);
    }

    /**
     * Get live location of driver for an order.
     */
    public function liveLocation(Order $order): JsonResponse
    {
        // Check if user can view this order
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this order location',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 403);
        }

        if (!$order->driver) {
            return response()->json([
                'success' => false,
                'message' => 'No driver assigned to this order',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 400);
        }

        // Get latest location event
        $latestLocationEvent = $order->trackingEvents()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->ordered('desc')
            ->first();

        $driverLocation = null;
        if ($latestLocationEvent) {
            $driverLocation = [
                'latitude' => $latestLocationEvent->latitude,
                'longitude' => $latestLocationEvent->longitude,
                'address' => $latestLocationEvent->location_address,
                'updated_at' => $latestLocationEvent->event_time,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Live location retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => [
                'order_id' => $order->id,
                'driver' => [
                    'id' => $order->driver->id,
                    'name' => $order->driver->name,
                    'phone' => $order->driver->phone ?? null,
                ],
                'current_location' => $driverLocation,
                'pickup_location' => [
                    'address' => $order->pickup_address,
                    'latitude' => $order->pickup_latitude,
                    'longitude' => $order->pickup_longitude,
                ],
                'delivery_location' => [
                    'address' => $order->delivery_address,
                    'latitude' => $order->delivery_latitude,
                    'longitude' => $order->delivery_longitude,
                ],
                'estimated_arrival' => $order->estimated_delivery_time,
            ],
        ]);
    }

    /**
     * Add a tracking event (for drivers/system).
     */
    public function addEvent(Request $request, Order $order): JsonResponse
    {
        // Only drivers assigned to the order or system can add events
        if ($order->driver_id !== auth()->id() && !auth()->user()->hasRole(['admin', 'dispatcher'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to add tracking events to this order',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $request->validate([
            'event_type' => 'required|string|max:50',
            'event_title' => 'required|string|max:255',
            'event_description' => 'nullable|string|max:500',
            'event_category' => 'nullable|in:order,payment,delivery,system',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_address' => 'nullable|string|max:255',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'url',
            'metadata' => 'nullable|array',
            'is_milestone' => 'nullable|boolean',
        ]);

        $event = OrderTrackingEvent::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'event_type' => $request->event_type,
            'event_title' => $request->event_title,
            'event_description' => $request->event_description,
            'event_category' => $request->event_category ?? 'delivery',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_address' => $request->location_address,
            'attachments' => $request->attachments,
            'metadata' => $request->metadata,
            'is_milestone' => $request->is_milestone ?? false,
            'event_time' => now(),
        ]);

        $event->load('user');

        // TODO: Send real-time notification to customer via WebSocket/Pusher

        return response()->json([
            'success' => true,
            'message' => 'Tracking event added successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $event,
        ], 201);
    }

    /**
     * Get tracking summary for multiple orders.
     */
    public function trackMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => 'required|array|max:10',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $orders = Order::whereIn('id', $request->order_ids)
            ->where('user_id', auth()->id())
            ->with(['driver', 'service'])
            ->get();

        $trackingSummary = $orders->map(function ($order) {
            $latestEvent = $order->trackingEvents()
                ->visibleToCustomer()
                ->ordered('desc')
                ->first();

            return [
                'order_id' => $order->id,
                'status' => $order->status,
                'status_display' => $this->getStatusDisplay($order->status),
                'progress_percentage' => $this->getProgressPercentage($order->status),
                'latest_event' => $latestEvent,
                'estimated_delivery' => $order->estimated_delivery_time,
                'driver' => $order->driver ? [
                    'name' => $order->driver->name,
                    'phone' => $order->driver->phone ?? null,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Multiple order tracking retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $trackingSummary,
        ]);
    }

    /**
     * Get status display name.
     */
    private function getStatusDisplay(string $status): string
    {
        return match ($status) {
            'pending' => 'Order Placed',
            'confirmed' => 'Order Confirmed',
            'driver_assigned' => 'Driver Assigned',
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get progress percentage based on status.
     */
    private function getProgressPercentage(string $status): int
    {
        return match ($status) {
            'pending' => 10,
            'confirmed' => 25,
            'driver_assigned' => 40,
            'picked_up' => 60,
            'in_transit' => 80,
            'delivered' => 100,
            'cancelled' => 0,
            default => 0,
        };
    }

    /**
     * Build timeline from events.
     */
    private function buildTimeline($events): array
    {
        return $events->map(function ($event) {
            return [
                'id' => $event->id,
                'type' => $event->event_type,
                'title' => $event->event_title,
                'description' => $event->event_description,
                'icon' => $event->event_icon,
                'color' => $event->event_color,
                'time' => $event->event_time,
                'time_formatted' => $event->event_time->format('M j, Y g:i A'),
                'time_ago' => $event->event_time->diffForHumans(),
                'is_milestone' => $event->is_milestone,
                'has_location' => $event->hasLocation(),
                'location' => $event->formatted_location,
                'attachments' => $event->attachments,
            ];
        })->toArray();
    }
}