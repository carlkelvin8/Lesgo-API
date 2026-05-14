<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Http\Controllers\Api\OrderController;
use App\Http\Requests\FilterOrderRequest;
use Illuminate\Http\Request;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing API Response Format...\n\n";

// Find a customer user
$user = User::where('role', 'customer')->first();
if (!$user) {
    echo "❌ No customer user found\n";
    exit(1);
}

echo "👤 Testing for user: {$user->name} ({$user->email})\n\n";

// Test the query directly (same as OrderController)
$query = \App\Models\Order::where('customer_id', $user->id)->with([
    'customer:id,name,email,phone_number',
    'partner:id,name',
    'driverProfile:id,user_id,status,rating',
    'driverProfile.user:id,name,email,phone_number',
    'service:id,name,code,icon_url',
    'lesbuyItems:id,order_id,name,quantity,unit,estimated_price,actual_price,image_url',
]);

$orders = $query->orderByDesc('id')->take(3)->get();

echo "✅ Found " . $orders->count() . " orders\n\n";

foreach ($orders as $order) {
    echo "📦 Order #{$order->id} - Service ID: {$order->service_id}\n";
    
    // Convert to array (like API response)
    $orderArray = $order->toArray();
    
    if (isset($orderArray['lesbuy_items']) && !empty($orderArray['lesbuy_items'])) {
        echo "   ✅ API Response has lesbuy_items: " . count($orderArray['lesbuy_items']) . "\n";
        foreach ($orderArray['lesbuy_items'] as $item) {
            echo "      - {$item['name']} (qty: {$item['quantity']}, estimated: {$item['estimated_price']}, actual: {$item['actual_price']})\n";
        }
    } else {
        echo "   ❌ No lesbuy_items in API response\n";
    }
    echo "\n";
}

echo "✅ API response test completed!\n";