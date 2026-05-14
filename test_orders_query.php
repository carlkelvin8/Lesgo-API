<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Order;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing Orders Query with LesBuy Items...\n\n";

// Find a customer user
$user = User::where('role', 'customer')->first();
if (!$user) {
    echo "❌ No customer user found\n";
    exit(1);
}

echo "👤 Testing for user: {$user->name} ({$user->email})\n\n";

// Simulate the same query as the OrderController
$query = Order::where('customer_id', $user->id)->with([
    'customer:id,name,email,phone_number',
    'partner:id,name',
    'driverProfile:id,user_id,status,rating',
    'driverProfile.user:id,name,email,phone_number',
    'service:id,name,code,icon_url',
    'lesbuyItems:id,order_id,name,quantity,unit,estimated_price,actual_price,image_url',
]);

$orders = $query->orderByDesc('id')->take(5)->get();

echo "✅ Found " . $orders->count() . " orders\n\n";

foreach ($orders as $order) {
    $serviceName = $order->service ? $order->service->name : 'Unknown';
    echo "📦 Order #{$order->id} - Service: {$serviceName} (ID: {$order->service_id})\n";
    echo "   Status: {$order->status}\n";
    echo "   Created: {$order->created_at}\n";
    
    if ($order->lesbuyItems->count() > 0) {
        echo "   ✅ LesBuy Items: {$order->lesbuyItems->count()}\n";
        foreach ($order->lesbuyItems as $item) {
            $price = $item->actual_price ?? $item->estimated_price ?? 'N/A';
            echo "      - {$item->name} (qty: {$item->quantity}, price: PHP {$price})\n";
        }
    } else {
        echo "   ❌ No LesBuy items\n";
    }
    echo "\n";
}

// Test JSON serialization (like API response)
echo "🔍 Testing JSON serialization...\n";
$orderWithItems = $orders->first(function ($order) {
    return $order->lesbuyItems->count() > 0;
});

if ($orderWithItems) {
    $json = $orderWithItems->toArray();
    echo "✅ Order #{$orderWithItems->id} serialized to JSON\n";
    echo "   LesBuy Items in JSON: " . (isset($json['lesbuy_items']) ? count($json['lesbuy_items']) : 0) . "\n";
    
    if (isset($json['lesbuy_items']) && !empty($json['lesbuy_items'])) {
        echo "   Sample item: " . $json['lesbuy_items'][0]['name'] . " - PHP " . $json['lesbuy_items'][0]['actual_price'] . "\n";
    }
} else {
    echo "❌ No orders with items found for JSON test\n";
}

echo "\n✅ Query test completed!\n";