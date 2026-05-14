<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\LesbuyItem;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "All Orders with LesBuy Items:\n\n";

$orders = Order::with(['lesbuyItems', 'customer:id,name,email'])
    ->whereHas('lesbuyItems')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();

foreach ($orders as $order) {
    $customerName = $order->customer ? $order->customer->name : 'Unknown';
    echo "Order #{$order->id} - Customer: {$customerName} (ID: {$order->customer_id}) - Service: {$order->service_id} - Status: {$order->status}\n";
    echo "  Created: {$order->created_at}\n";
    echo "  Pickup: {$order->pickup_address}\n";
    echo "  Dropoff: {$order->dropoff_address}\n";
    echo "  Fare: {$order->estimated_fare} (actual: " . ($order->actual_fare ?? 'null') . ")\n";
    echo "  Items ({$order->lesbuyItems->count()}):\n";
    foreach ($order->lesbuyItems as $item) {
        $price = $item->actual_price ?? $item->estimated_price ?? 'null';
        echo "    - {$item->name} (qty: {$item->quantity}, unit: {$item->unit}, price: {$price})\n";
    }
    echo "\n";
}

if ($orders->isEmpty()) {
    echo "No orders with items found.\n";
    echo "\nChecking all LesbuyItems:\n";
    $items = LesbuyItem::with('order')->orderBy('id', 'desc')->take(10)->get();
    foreach ($items as $item) {
        $price = $item->actual_price ?? $item->estimated_price ?? 'null';
        echo "Item: {$item->name} - Order #{$item->order_id} - Price: {$price}\n";
    }
    
    if ($items->isEmpty()) {
        echo "No LesbuyItems found in database.\n";
    }
}

echo "\nTotal Orders: " . Order::count() . "\n";
echo "Total LesbuyItems: " . LesbuyItem::count() . "\n";