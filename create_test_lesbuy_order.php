<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Order;
use App\Models\LesbuyItem;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🛒 Creating Test LesBuy Order with Items...\n\n";

// Find a customer user
$user = User::where('role', 'customer')->first();
if (!$user) {
    echo "❌ No customer user found\n";
    exit(1);
}

echo "👤 Creating order for user: {$user->name} ({$user->email})\n";

// Create a LesBuy order (service_id = 4)
$order = Order::create([
    'customer_id' => $user->id,
    'service_id' => 4, // LesBuy
    'pickup_address' => 'SM Mall of Asia, Pasay City',
    'pickup_lat' => 14.5352,
    'pickup_lng' => 120.9822,
    'pickup_contact_name' => $user->name,
    'pickup_contact_phone' => $user->phone_number ?? '+639123456789',
    'dropoff_address' => '123 Test Street, Makati City',
    'dropoff_lat' => 14.5547,
    'dropoff_lng' => 121.0244,
    'dropoff_contact_name' => $user->name,
    'dropoff_contact_phone' => $user->phone_number ?? '+639123456789',
    'notes' => 'Test LesBuy order with items',
    'status' => 'completed',
    'estimated_distance_m' => 5000,
    'estimated_fare' => 250.00,
    'actual_fare' => 250.00,
    'payment_method' => 'cash',
    'payment_status' => 'paid',
    'completed_at' => now(),
]);

echo "📦 Created Order #{$order->id}\n";

// Create test items for the order
$items = [
    [
        'name' => 'Apple iPhone 15',
        'quantity' => 1,
        'unit' => 'piece',
        'estimated_price' => 50000.00,
        'actual_price' => 48000.00,
    ],
    [
        'name' => 'Samsung Galaxy Buds',
        'quantity' => 2,
        'unit' => 'pair',
        'estimated_price' => 8000.00,
        'actual_price' => 7500.00,
    ],
    [
        'name' => 'Phone Case',
        'quantity' => 1,
        'unit' => 'piece',
        'estimated_price' => 1500.00,
        'actual_price' => 1200.00,
    ],
];

foreach ($items as $itemData) {
    $item = LesbuyItem::create([
        'order_id' => $order->id,
        'name' => $itemData['name'],
        'quantity' => $itemData['quantity'],
        'unit' => $itemData['unit'],
        'estimated_price' => $itemData['estimated_price'],
        'actual_price' => $itemData['actual_price'],
    ]);
    
    echo "   ✅ Added item: {$item->name} (qty: {$item->quantity}, price: PHP {$item->actual_price})\n";
}

echo "\n🎉 Test LesBuy order created successfully!\n";
echo "Order ID: {$order->id}\n";
echo "Total Items: " . count($items) . "\n";

// Test the API response
echo "\n🔍 Testing API response...\n";
$orderWithItems = Order::with('lesbuyItems')->find($order->id);
echo "Items loaded: " . $orderWithItems->lesbuyItems->count() . "\n";

foreach ($orderWithItems->lesbuyItems as $item) {
    echo "  - {$item->name}: PHP {$item->actual_price} x {$item->quantity}\n";
}

echo "\n✅ Test completed!\n";