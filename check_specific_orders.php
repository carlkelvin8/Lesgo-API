<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\LesbuyItem;
use App\Models\User;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking Specific Test Orders\n";
echo str_repeat('=', 60) . "\n\n";

// Find the test user
$testUser = User::find(16); // User ID 16 from the API response

if (!$testUser) {
    echo "❌ Test user not found!\n";
    exit;
}

echo "👤 Test User: {$testUser->name} (ID: {$testUser->id})\n\n";

// Get orders for this user
$orders = Order::where('customer_id', $testUser->id)
    ->with(['lesbuyItems', 'service'])
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();

echo "📦 Orders for test user:\n";
foreach ($orders as $order) {
    echo "   Order #{$order->id} - {$order->service->name} ({$order->service->code})\n";
    echo "      Status: {$order->status}\n";
    echo "      Created: {$order->created_at}\n";
    echo "      Items: {$order->lesbuyItems->count()}\n";
    
    if ($order->lesbuyItems->count() > 0) {
        foreach ($order->lesbuyItems as $item) {
            $price = $item->actual_price ?? $item->estimated_price ?? 'null';
            echo "         ✅ {$item->name} (qty: {$item->quantity}, price: {$price})\n";
        }
    } else {
        echo "         ❌ No items found\n";
        
        // Check if items exist but aren't being loaded
        $itemCount = LesbuyItem::where('order_id', $order->id)->count();
        if ($itemCount > 0) {
            echo "         ⚠️  But {$itemCount} items exist in database!\n";
            $items = LesbuyItem::where('order_id', $order->id)->get();
            foreach ($items as $item) {
                echo "            - {$item->name} (qty: {$item->quantity})\n";
            }
        }
    }
    echo "\n";
}

// Test the API response format
echo "🔍 Testing API response format for Order #45:\n";
$order45 = Order::with([
    'customer:id,name,email,phone_number',
    'partner:id,name',
    'driverProfile:id,user_id,status,rating',
    'driverProfile.user:id,name,email,phone_number',
    'service:id,name,code,icon_url',
    'lesbuyItems:id,order_id,name,quantity,unit,estimated_price,actual_price,image_url',
])->find(45);

if ($order45) {
    echo "   Order found: #{$order45->id}\n";
    echo "   Service: {$order45->service->name}\n";
    echo "   Items loaded: {$order45->lesbuyItems->count()}\n";
    
    if ($order45->lesbuyItems->count() > 0) {
        foreach ($order45->lesbuyItems as $item) {
            echo "      - {$item->name}\n";
        }
    }
    
    // Convert to array like the API does
    $orderArray = $order45->toArray();
    echo "   Has lesbuy_items in array: " . (isset($orderArray['lesbuy_items']) ? 'YES' : 'NO') . "\n";
    if (isset($orderArray['lesbuy_items'])) {
        echo "   Items in array: " . count($orderArray['lesbuy_items']) . "\n";
    }
} else {
    echo "   Order #45 not found\n";
}

echo "\n✅ Check complete!\n";