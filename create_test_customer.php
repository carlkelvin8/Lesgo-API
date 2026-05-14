<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Order;
use App\Models\LesbuyItem;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "👤 Creating Test Customer User...\n\n";

// Create or find the test customer user
$user = User::where('email', 'test@example.com')->first();
if (!$user) {
    $user = new User();
    $user->name = 'Test Customer';
    $user->email = 'test@example.com';
    $user->role = 'customer';
    $user->phone_number = '+639123456789';
    $user->email_verified_at = now();
    $user->password = bcrypt('password123');
    $user->save();
    echo "✅ Created new customer user: {$user->name} ({$user->email})\n";
} else {
    echo "✅ Found existing customer user: {$user->name} ({$user->email})\n";
}

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

echo "\n🛒 Creating Test LesBuy Order...\n";

// Create a LesBuy order (service_id = 4)
$order = Order::create([
    'customer_id' => $user->id,
    'service_id' => 4, // LesBuy
    'pickup_address' => 'SM Megamall, Mandaluyong City',
    'pickup_lat' => 14.5856,
    'pickup_lng' => 121.0566,
    'pickup_contact_name' => $user->name,
    'pickup_contact_phone' => $user->phone_number ?? '+639123456789',
    'dropoff_address' => '789 Customer Street, Pasig City',
    'dropoff_lat' => 14.5764,
    'dropoff_lng' => 121.0851,
    'dropoff_contact_name' => $user->name,
    'dropoff_contact_phone' => $user->phone_number ?? '+639123456789',
    'notes' => 'Test LesBuy order for app customer',
    'status' => 'completed',
    'estimated_distance_m' => 6000,
    'estimated_fare' => 280.00,
    'actual_fare' => 280.00,
    'payment_method' => 'cash',
    'payment_status' => 'paid',
    'completed_at' => now(),
]);

echo "📦 Created Order #{$order->id}\n";

// Create test items for the order
$items = [
    [
        'name' => 'Gaming Laptop ASUS ROG',
        'quantity' => 1,
        'unit' => 'piece',
        'estimated_price' => 85000.00,
        'actual_price' => 82000.00,
    ],
    [
        'name' => 'Wireless Gaming Mouse',
        'quantity' => 1,
        'unit' => 'piece',
        'estimated_price' => 3500.00,
        'actual_price' => 3200.00,
    ],
    [
        'name' => 'Mechanical Keyboard',
        'quantity' => 1,
        'unit' => 'piece',
        'estimated_price' => 8000.00,
        'actual_price' => 7500.00,
    ],
    [
        'name' => 'Gaming Headset',
        'quantity' => 2,
        'unit' => 'piece',
        'estimated_price' => 4500.00,
        'actual_price' => 4200.00,
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
echo "Customer: {$user->name} ({$user->email})\n";

echo "\n✅ Test completed! The app should now show item details in transactions.\n";