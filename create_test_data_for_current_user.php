<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Order;
use App\Models\LesbuyItem;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🛒 Creating Test LesBuy Order for Current App User...\n\n";

// Find the test user that the app is using
$user = User::where('email', 'test@example.com')->first();
if (!$user) {
    echo "❌ User test@example.com not found. Let me check what users exist:\n";
    $users = User::take(5)->get();
    foreach ($users as $u) {
        echo "   - {$u->name} ({$u->email}) - Role: {$u->role}\n";
    }
    exit(1);
}

echo "👤 Creating order for user: {$user->name} ({$user->email})\n";

// Check if this user already has LesBuy orders
$existingOrders = $user->customerOrders()->where('service_id', 4)->with('lesbuyItems')->get();
if ($existingOrders->isNotEmpty()) {
    echo "✅ User already has " . $existingOrders->count() . " LesBuy orders:\n";
    foreach ($existingOrders as $order) {
        echo "   - Order #{$order->id} with {$order->lesbuyItems->count()} items\n";
    }
    echo "\n🎉 Test data already exists!\n";
    exit(0);
}

// Create a LesBuy order (service_id = 4)
$order = Order::create([
    'customer_id' => $user->id,
    'service_id' => 4, // LesBuy
    'pickup_address' => 'Robinsons Galleria, Quezon City',
    'pickup_lat' => 14.6199,
    'pickup_lng' => 121.0560,
    'pickup_contact_name' => $user->name,
    'pickup_contact_phone' => $user->phone_number ?? '+639123456789',
    'dropoff_address' => '456 Test Avenue, Makati City',
    'dropoff_lat' => 14.5547,
    'dropoff_lng' => 121.0244,
    'dropoff_contact_name' => $user->name,
    'dropoff_contact_phone' => $user->phone_number ?? '+639123456789',
    'notes' => 'Test LesBuy order for app user',
    'status' => 'completed',
    'estimated_distance_m' => 8000,
    'estimated_fare' => 350.00,
    'actual_fare' => 350.00,
    'payment_method' => 'cash',
    'payment_status' => 'paid',
    'completed_at' => now(),
]);

echo "📦 Created Order #{$order->id}\n";

// Create test items for the order
$items = [
    [
        'name' => 'MacBook Pro 14-inch',
        'quantity' => 1,
        'unit' => 'piece',
        'estimated_price' => 120000.00,
        'actual_price' => 115000.00,
    ],
    [
        'name' => 'AirPods Pro',
        'quantity' => 2,
        'unit' => 'pair',
        'estimated_price' => 15000.00,
        'actual_price' => 14500.00,
    ],
    [
        'name' => 'USB-C Cable',
        'quantity' => 3,
        'unit' => 'piece',
        'estimated_price' => 2000.00,
        'actual_price' => 1800.00,
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

echo "\n✅ Test completed! The app should now show item details in transactions.\n";