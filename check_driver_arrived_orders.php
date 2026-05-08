<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 CHECKING DRIVER_ARRIVED_AT_PICKUP ORDERS\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$orders = Order::where('status', 'driver_arrived_at_pickup')->get();

echo "Orders in driver_arrived_at_pickup status: {$orders->count()}\n\n";

foreach ($orders as $order) {
    echo "Order #{$order->id}:\n";
    echo "  Driver ID: {$order->driver_id}\n";
    echo "  Driver Share: " . ($order->driver_share ?? 'null') . "\n";
    echo "  Estimated Fare: {$order->estimated_fare}\n";
    
    if ($order->driver_id && $order->driver_share === null) {
        echo "  ❌ NEEDS FIXING: Has driver but no driver_share\n";
    } else {
        echo "  ✅ OK\n";
    }
    echo "\n";
}