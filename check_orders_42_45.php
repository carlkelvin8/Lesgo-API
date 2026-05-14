<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\LesbuyItem;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking Orders #42-45\n";
echo str_repeat('=', 60) . "\n\n";

// Check orders 42-45
for ($orderId = 42; $orderId <= 45; $orderId++) {
    echo "📦 Order #{$orderId}:\n";
    
    $order = Order::with(['lesbuyItems', 'customer:id,name,email', 'service'])
        ->find($orderId);
    
    if ($order) {
        echo "   ✅ Found: {$order->service->name} for {$order->customer->name}\n";
        echo "   Customer ID: {$order->customer_id}\n";
        echo "   Service ID: {$order->service_id}\n";
        echo "   Status: {$order->status}\n";
        echo "   Created: {$order->created_at}\n";
        echo "   Items: {$order->lesbuyItems->count()}\n";
        
        if ($order->lesbuyItems->count() > 0) {
            foreach ($order->lesbuyItems as $item) {
                $price = $item->actual_price ?? $item->estimated_price ?? 'null';
                echo "      ✅ {$item->name} (qty: {$item->quantity}, price: {$price})\n";
            }
        } else {
            echo "      ❌ No items found\n";
            
            // Check if items exist but aren't being loaded
            $itemCount = LesbuyItem::where('order_id', $orderId)->count();
            if ($itemCount > 0) {
                echo "      ⚠️  But {$itemCount} items exist in database!\n";
                $items = LesbuyItem::where('order_id', $orderId)->get();
                foreach ($items as $item) {
                    echo "         - {$item->name} (qty: {$item->quantity})\n";
                }
            }
        }
        
        // Test API response format
        echo "   🔍 Testing API response format:\n";
        $orderArray = $order->toArray();
        echo "      Has lesbuy_items in array: " . (isset($orderArray['lesbuy_items']) ? 'YES' : 'NO') . "\n";
        if (isset($orderArray['lesbuy_items'])) {
            echo "      Items in array: " . count($orderArray['lesbuy_items']) . "\n";
        }
        
    } else {
        echo "   ❌ Not found\n";
    }
    echo "\n";
}

echo "✅ Check complete!\n";