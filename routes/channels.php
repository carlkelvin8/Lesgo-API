<?php

use App\Models\Order;
use App\Models\ChatConversation;
use App\Models\Geofence;
use Illuminate\Support\Facades\Broadcast;

// Wrap all channel registrations in try/catch to prevent build failures
// when broadcasting env vars (REVERB_APP_KEY etc.) are not yet available.
try {

// Default user model channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── REAL-TIME CHANNELS ──────────────────────────────────────────────────

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('driver.{driverId}', function ($user, $driverId) {
    return (int) $user->id === (int) $driverId && $user->isDriver();
});

Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = Order::find($orderId);
    if (!$order) return false;
    return (int) $user->id === (int) $order->customer_id ||
           (int) $user->id === (int) $order->driver_id ||
           $user->isAdmin();
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = ChatConversation::find($conversationId);
    if (!$conversation) return false;
    return (int) $user->id === (int) $conversation->customer_id ||
           (int) $user->id === (int) $conversation->driver_id ||
           $user->isAdmin();
});

Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('geofence.{geofenceId}', function ($user, $geofenceId) {
    $geofence = Geofence::find($geofenceId);
    if (!$geofence) return false;
    return (int) $user->id === (int) $geofence->created_by || $user->isAdmin();
});

Broadcast::channel('drivers.nearby', function () {
    return true;
});

Broadcast::channel('orders.customer.{customerId}', function ($user, $customerId) {
    return (int) $user->id === (int) $customerId;
});

Broadcast::channel('orders.driver.{driverProfileId}', function ($user, $driverProfileId) {
    return (int) optional($user->driverProfile)->id === (int) $driverProfileId;
});

Broadcast::channel('orders.partner.{partnerId}', function ($user, $partnerId) {
    if ($user->isAdmin()) return true;
    return $user->isPartnerAdmin() && (int) optional($user->partner)->id === (int) $partnerId;
});

Broadcast::channel('drivers.{driverProfileId}.location', function ($user, $driverProfileId) {
    if ($user->isAdmin()) return true;
    if ((int) optional($user->driverProfile)->id === (int) $driverProfileId) {
        return true;
    }
    return Order::where('driver_id', $driverProfileId)
        ->where('customer_id', $user->id)
        ->whereIn('status', ['accepted', 'picked_up'])
        ->exists();
});

} catch (\Throwable $e) {
    // Broadcasting not available during build — silently skip
}
