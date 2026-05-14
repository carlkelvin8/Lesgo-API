<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Order;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Orders API for testcustomer@lesgo.com\n\n";

$user = User::where('email', 'testcustomer@lesgo.com')->first();
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User found: {$user->name} (ID: {$user->id})\n";

// Test the scoped query logic from OrderController
$orders = Order::where('customer_id', $user->id)
    ->with([
        'customer:id,name,email,phone_number',
        'partner:id,name',
        'driverProfile:id,user_id,status,rating',
        'driverProfile.user:id,name,email,phone_number',
        'service:id,name,code,icon_url',
        'lesbuyItems:id,order_id,name,quantity,unit,estimated_price,actual_price,image_url',
    ])
    ->orderByDesc('id')
    ->get();

echo "📦 Found {$orders->count()} orders for this customer:\n\n";

foreach ($orders as $order) {
    echo "Order #{$order->id}:\n";
    echo "  - Service: {$order->service_id}\n";
    echo "  - Status: {$order->status}\n";
    echo "  - Created: {$order->created_at}\n";
    echo "  - Items: {$order->lesbuyItems->count()}\n";
    foreach ($order->lesbuyItems as $item) {
        echo "    * {$item->name} (qty: {$item->quantity}, price: " . ($item->actual_price ?? $item->estimated_price ?? 'null') . ")\n";
    }
    echo "\n";
}

if ($orders->isEmpty()) {
    echo "ℹ️  No orders found for this customer. Creating a test order...\n";
    
    $order = new Order([
        'customer_id' => $user->id,
        'service_id' => 4, // LesBuy
        'pickup_address' => 'Test Store, Cebu City',
        'pickup_lat' => 10.3157,
        'pickup_lng' => 123.9065,
        'pickup_contact_name' => $user->name,
        'pickup_contact_phone' => $user->phone_number ?? '+639123456789',
        'dropoff_address' => 'Test Address, Cebu City',
        'dropoff_lat' => 10.3267,
        'dropoff_lng' => 123.9065,
        'dropoff_contact_name' => $user->name,
        'dropoff_contact_phone' => $user->phone_number ?? '+639123456789',
        'status' => 'completed',
        'estimated_distance_m' => 2500,
        'estimated_fare' => 125.50,
        'actual_fare' => 125.50,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);
    
    $order->save();
    
    // Add items
    $order->lesbuyItems()->create([
        'name' => 'Test Item 1',
        'quantity' => 2,
        'unit' => 'piece',
        'estimated_price' => 50.00,
        'actual_price' => 45.00,
        'status' => 'completed',
    ]);
    
    $order->lesbuyItems()->create([
        'name' => 'Test Item 2',
        'quantity' => 1,
        'unit' => 'kg',
        'estimated_price' => 80.00,
        'actual_price' => 80.50,
        'status' => 'completed',
    ]);
    
    echo "✅ Test order #{$order->id} created with 2 items\n";
}