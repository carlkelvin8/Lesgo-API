<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Order;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing LesBuy Items in Orders...\n\n";

// Find a customer user
$user = User::where('email', 'customer@example.com')->first();
if (!$user) {
    $user = User::where('role', 'customer')->first();
}

if (!$user) {
    echo "❌ No customer user found\n";
    exit(1);
}

echo "👤 Testing with user: {$user->name} ({$user->email})\n\n";

// Get orders with lesbuy items
$orders = $user->customerOrders()->with(['lesbuyItems', 'service'])->take(5)->get();

if ($orders->isEmpty()) {
    echo "❌ No orders found for this user\n";
    exit(1);
}

foreach ($orders as $order) {
    $serviceName = $order->service ? $order->service->name : 'Unknown';
    echo "📦 Order #{$order->id} - Service: {$serviceName} (ID: {$order->service_id})\n";
    echo "   Status: {$order->status}\n";
    echo "   Created: {$order->created_at}\n";
    
    if ($order->lesbuyItems->count() > 0) {
        echo "   ✅ Items: {$order->lesbuyItems->count()}\n";
        foreach ($order->lesbuyItems as $item) {
            $price = $item->actual_price ?? $item->estimated_price ?? 'N/A';
            echo "      - {$item->name} (qty: {$item->quantity}, price: PHP {$price})\n";
        }
    } else {
        echo "   ❌ No items found\n";
    }
    echo "\n";
}

echo "✅ Test completed!\n";