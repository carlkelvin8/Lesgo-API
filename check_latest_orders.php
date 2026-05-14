<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\LesbuyItem;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking Latest Orders\n";
echo str_repeat('=', 60) . "\n\n";

// Get the latest orders
$latestOrders = Order::with(['lesbuyItems', 'customer:id,name,email', 'service'])
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();

echo "📦 Latest 10 Orders:\n";
foreach ($latestOrders as $order) {
    echo "   Order #{$order->id} - {$order->service->name}\n";
    echo "      Customer: {$order->customer->name} (ID: {$order->customer_id})\n";
    echo "      Status: {$order->status}\n";
    echo "      Created: {$order->created_at}\n";
    echo "      Items: {$order->lesbuyItems->count()}\n";
    
    if ($order->lesbuyItems->count() > 0) {
        foreach ($order->lesbuyItems as $item) {
            $price = $item->actual_price ?? $item->estimated_price ?? 'null';
            echo "         ✅ {$item->name} (qty: {$item->quantity}, price: {$price})\n";
        }
    } else {
        echo "         ❌ No items\n";
    }
    echo "\n";
}

// Check the highest order ID
$maxOrderId = Order::max('id');
echo "🔢 Highest Order ID: {$maxOrderId}\n";

// Check if there are any orders without items that should have them
echo "\n🔍 Orders without items (service_id 3 or 4):\n";
$ordersWithoutItems = Order::whereIn('service_id', [3, 4])
    ->whereDoesntHave('lesbuyItems')
    ->with(['customer:id,name,email', 'service'])
    ->orderBy('id', 'desc')
    ->take(5)
    ->get();

foreach ($ordersWithoutItems as $order) {
    echo "   Order #{$order->id} - {$order->service->name}\n";
    echo "      Customer: {$order->customer->name}\n";
    echo "      Status: {$order->status}\n";
    echo "      Created: {$order->created_at}\n";
    echo "\n";
}

echo "✅ Check complete!\n";