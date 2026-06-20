<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== PENDING ORDERS ===\n\n";

$orders = App\Models\Order::where('status', 'pending')
    ->with(['customer', 'service', 'driverProfile'])
    ->get();

if ($orders->isEmpty()) {
    echo "No pending orders found.\n";
} else {
    foreach ($orders as $order) {
        echo "Order #{$order->id}\n";
        echo "  Customer: {$order->customer->name}\n";
        echo "  Service: {$order->service->name}\n";
        echo "  Status: {$order->status}\n";
        echo "  Fare: ₱{$order->estimated_fare}\n";
        echo "  Driver: " . ($order->driver_id ? "ID {$order->driver_id}" : "None") . "\n";
        echo "  Created: {$order->created_at}\n";
        echo "\n";
    }
}

echo "\n=== RIDER WITH PHONE 09123456789 ===\n\n";

$rider = App\Models\User::where('phone_number', '09123456789')
    ->where('role', 'driver')
    ->with(['driverProfile', 'wallet'])
    ->first();

if ($rider) {
    echo "Rider: {$rider->name} (ID: {$rider->id})\n";
    echo "Email: {$rider->email}\n";
    echo "Driver Profile ID: " . ($rider->driverProfile?->id ?? 'None') . "\n";
    echo "Wallet Balance: ₱" . ($rider->wallet?->balance ?? '0.00') . "\n";
    echo "Status: " . ($rider->driverProfile?->status ?? 'N/A') . "\n";
} else {
    echo "Rider not found.\n";
}
