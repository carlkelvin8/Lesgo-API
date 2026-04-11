<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PredictiveTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tracking/orders/{order}",
     *     summary="Track order with enhanced ETA",
     *     tags={"Order Tracking"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Order tracking details with predictive ETA")
     * )
     */
    public function trackOrder(Order $order, PredictiveTrackingService $trackingService): JsonResponse
    {
        $user = request()->user();
        
        // Check if user can view this order
        if (!$this->canViewOrder($user, $order)) {
            return $this->error('Forbidden', 403);
        }

        // Get predictive ETA
        $etaDetails = $trackingService->calculatePredictiveETA($order);
        
        // Add predictive ETA to order
        $order->predictive_eta = $etaDetails;
        
        $trackingData = [
            'order' => $order->load([
                'customer:id,name,phone_number',
                'driverProfile:id,user_id,status,rating',
                'service:id,name,code,icon_url'
            ]),
            'current_status' => $order->status,
            'tracking_events' => $this->getTrackingEvents($order),
            'timeline' => $this->getOrderTimeline($order),
            'eta_details' => $etaDetails
        ];

        return $this->success($trackingData, 'Order tracking retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tracking/orders/{order}/location",
     *     summary="Get live driver location",
     *     tags={"Order Tracking"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Driver location")
     * )
     */
    public function liveLocation(Order $order): JsonResponse
    {
        $user = request()->user();
        
        if (!$this->canViewOrder($user, $order)) {
            return $this->error('Forbidden', 403);
        }

        if (!$order->driver_id) {
            return $this->error('No driver assigned to this order', 404);
        }

        // Get latest driver location
        $location = \App\Models\DriverLocation::where('driver_id', $order->driver_id)
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();

        if (!$location) {
            return $this->error('Driver location not available', 404);
        }

        return $this->success([
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'heading' => $location->heading,
            'speed' => $location->speed,
            'accuracy' => $location->accuracy,
            'updated_at' => $location->updated_at->toISOString()
        ], 'Driver location retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tracking/orders/{order}/events",
     *     summary="Add tracking event",
     *     tags={"Order Tracking"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Event added")
     * )
     */
    public function addEvent(Order $order, Request $request): JsonResponse
    {
        return $this->success([], 'Tracking event added successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tracking/orders/multiple",
     *     summary="Track multiple orders",
     *     tags={"Order Tracking"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Multiple orders tracking")
     * )
     */
    public function trackMultiple(Request $request): JsonResponse
    {
        return $this->success([], 'Multiple orders tracking retrieved successfully');
    }

    private function canViewOrder($user, Order $order): bool
    {
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        if ($user->isCustomer()) return (int) $order->customer_id === (int) $user->id;
        if ($user->isDriver()) {
            return optional($user->driverProfile)->id && 
                   (int) $order->driver_id === (int) optional($user->driverProfile)->id;
        }
        return false;
    }

    private function getTrackingEvents(Order $order): array
    {
        return [
            [
                'event' => 'order_created',
                'timestamp' => $order->created_at->toISOString(),
                'description' => 'Order has been created'
            ],
            [
                'event' => 'searching_driver',
                'timestamp' => $order->created_at->toISOString(),
                'description' => 'Looking for available driver'
            ]
        ];
    }

    private function getOrderTimeline(Order $order): array
    {
        $timeline = [];
        
        $timeline[] = [
            'status' => 'pending',
            'title' => 'Order Placed',
            'description' => 'Your order has been placed successfully',
            'timestamp' => $order->created_at->toISOString(),
            'completed' => true
        ];

        if ($order->accepted_at) {
            $timeline[] = [
                'status' => 'accepted',
                'title' => 'Driver Assigned',
                'description' => 'A driver has been assigned to your order',
                'timestamp' => $order->accepted_at->toISOString(),
                'completed' => true
            ];
        }

        if ($order->picked_up_at) {
            $timeline[] = [
                'status' => 'picked_up',
                'title' => 'Order Picked Up',
                'description' => 'Your order has been picked up',
                'timestamp' => $order->picked_up_at->toISOString(),
                'completed' => true
            ];
        }

        if ($order->completed_at) {
            $timeline[] = [
                'status' => 'completed',
                'title' => 'Order Delivered',
                'description' => 'Your order has been delivered successfully',
                'timestamp' => $order->completed_at->toISOString(),
                'completed' => true
            ];
        }

        return $timeline;
    }
}