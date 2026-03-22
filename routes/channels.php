<?php

use App\Models\DriverProfile;
use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

// Default user model channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

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
