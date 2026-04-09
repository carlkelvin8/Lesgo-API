<?php

use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\ChatConversation;
use App\Models\Geofence;
use Illuminate\Support\Facades\Broadcast;

// Default user model channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── REAL-TIME CHANNELS ──────────────────────────────────────────────────

// User-specific channels
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Driver-specific channels
Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
    return (int) $user->id === (int) $driverId && $user->isDriver();
});

// Order-specific channels (customers and drivers can subscribe)
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = Order::find($orderId);
    if (!$order) return false;
    
    return (int) $user->id === (int) $order->customer_id || 
           (int) $user->id === (int) $order->driver_id ||
           $user->isAdmin();
});

// Chat conversation channels
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = ChatConversation::find($conversationId);
    if (!$conversation) return false;
    
    return (int) $user->id === (int) $conversation->customer_id || 
           (int) $user->id === (int) $conversation->driver_id ||
           $user->isAdmin();
});

// Notification channels
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Geofence channels
Broadcast::channel('geofence.{geofenceId}', function ($user, $geofenceId) {
    $geofence = Geofence::find($geofenceId);
    if (!$geofence) return false;
    
    return (int) $user->id === (int) $geofence->created_by || $user->isAdmin();
});

// Public channels (no authentication required)
Broadcast::channel('drivers.nearby', function () {
    return true; // Public channel for nearby driver discovery
});

// ── LEGACY CHANNELS (for backward compatibility) ──────────────────────────

// Customer receives order updates for their own orders
Broadcast::channel('orders.customer.{customerId}', function ($user, $customerId) {
    return (int) $user->id === (int) $customerId;
});

// Driver receives order updates for orders assigned to them
Broadcast::channel('orders.driver.{driverProfileId}', function ($user, $driverProfileId) {
    return (int) optional($user->driverProfile)->id === (int) $driverProfileId;
});

// Partner admin receives order updates for their partner's orders
Broadcast::channel('orders.partner.{partnerId}', function ($user, $partnerId) {
    if ($user->isAdmin()) return true;
    return $user->isPartnerAdmin() && (int) optional($user->partner)->id === (int) $partnerId;
});

// Driver location channel — driver updates their own, customer of active order can subscribe
Broadcast::channel('drivers.{driverProfileId}.location', function ($user, $driverProfileId) {
    if ($user->isAdmin()) return true;

    // Driver can subscribe to their own location channel
    if ((int) optional($user->driverProfile)->id === (int) $driverProfileId) {
        return true;
    }

    // Customer can subscribe if this driver is assigned to their active order
    return Order::where('driver_id', $driverProfileId)
        ->where('customer_id', $user->id)
        ->whereIn('status', ['accepted', 'picked_up'])
        ->exists();
});
