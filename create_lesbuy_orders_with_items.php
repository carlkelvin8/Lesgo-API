<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\User;
use App\Models\LesbuyItem;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              CREATE LESBUY ORDERS WITH ITEMS                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$user = User::where('email', 'carlkelvinmanahan014@gmail.com')->first();
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "👤 Customer: {$user->name} (ID: {$user->id})\n";
echo "\n";

// Create LesBuy Order 1 - Grocery Shopping
echo "🛒 Creating LesBuy Order 1 - Grocery Shopping...\n";
$order1 = Order::create([
    'customer_id' => $user->id,
    'service_id' => 4, // LesBuy
    'pickup_address' => 'SM City Cebu, North Reclamation Area, Cebu City',
    'pickup_lat' => 10.3157,
    'pickup_lng' => 123.9065,
    'pickup_contact_name' => $user->name,
    'pickup_contact_phone' => $user->phone_number,
    'dropoff_address' => 'IT Park, Lahug, Cebu City',
    'dropoff_lat' => 10.3267,
    'dropoff_lng' => 123.9065,
    'dropoff_contact_name' => $user->name,
    'dropoff_contact_phone' => $user->phone_number,
    'status' => 'completed',
    'estimated_distance_m' => 3500,
    'estimated_fare' => 185.50,
    'actual_fare' => 185.50,
    'payment_method' => 'cash',
    'payment_status' => 'paid',
    'completed_at' => now()->subHours(2),
    'created_at' => now()->subHours(3),
    'updated_at' => now()->subHours(2),
    'meta' => [
        'order_value' => 850.00,
        'grand_total' => 1035.50
    ]
]);

// Add items to Order 1
$items1 = [
    ['name' => 'Rice 5kg', 'quantity' => 1, 'unit' => 'bag', 'estimated_price' => 250.00, 'actual_price' => 245.00],
    ['name' => 'Chicken Breast', 'quantity' => 2, 'unit' => 'kg', 'estimated_price' => 180.00, 'actual_price' => 175.00],
    ['name' => 'Fresh Milk', 'quantity' => 3, 'unit' => 'liter', 'estimated_price' => 85.00, 'actual_price' => 85.00],
    ['name' => 'Bread Loaf', 'quantity' => 2, 'unit' => 'piece', 'estimated_price' => 45.00, 'actual_price' => 42.00],
    ['name' => 'Eggs', 'quantity' => 1, 'unit' => 'dozen', 'estimated_price' => 120.00, 'actual_price' => 118.00],
    ['name' => 'Cooking Oil', 'quantity' => 1, 'unit' => 'bottle', 'estimated_price' => 95.00, 'actual_price' => 90.00],
    ['name' => 'Onions', 'quantity' => 1, 'unit' => 'kg', 'estimated_price' => 75.00, 'actual_price' => 80.50],
];

foreach ($items1 as $item) {
    LesbuyItem::create([
        'order_id' => $order1->id,
        'name' => $item['name'],
        'quantity' => $item['quantity'],
        'unit' => $item['unit'],
        'estimated_price' => $item['estimated_price'],
        'actual_price' => $item['actual_price'],
        'status' => 'completed',
    ]);
}

echo "✅ Order #{$order1->id} created with " . count($items1) . " items\n";

// Create LesBuy Order 2 - Electronics Shopping
echo "📱 Creating LesBuy Order 2 - Electronics Shopping...\n";
$order2 = Order::create([
    'customer_id' => $user->id,
    'service_id' => 4, // LesBuy
    'pickup_address' => 'Ayala Center Cebu, Cebu Business Park',
    'pickup_lat' => 10.3181,
    'pickup_lng' => 123.9058,
    'pickup_contact_name' => $user->name,
    'pickup_contact_phone' => $user->phone_number,
    'dropoff_address' => 'Capitol Site, Cebu City',
    'dropoff_lat' => 10.2929,
    'dropoff_lng' => 123.9015,
    'dropoff_contact_name' => $user->name,
    'dropoff_contact_phone' => $user->phone_number,
    'status' => 'completed',
    'estimated_distance_m' => 2800,
    'estimated_fare' => 125.75,
    'actual_fare' => 125.75,
    'payment_method' => 'gcash',
    'payment_status' => 'paid',
    'completed_at' => now()->subDays(1),
    'created_at' => now()->subDays(1)->subHours(2),
    'updated_at' => now()->subDays(1),
    'meta' => [
        'order_value' => 1250.00,
        'grand_total' => 1375.75
    ]
]);

// Add items to Order 2
$items2 = [
    ['name' => 'Phone Charger Cable', 'quantity' => 2, 'unit' => 'piece', 'estimated_price' => 150.00, 'actual_price' => 145.00],
    ['name' => 'Power Bank 10000mAh', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 800.00, 'actual_price' => 750.00],
    ['name' => 'Phone Case', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 300.00, 'actual_price' => 280.00],
    ['name' => 'Screen Protector', 'quantity' => 2, 'unit' => 'piece', 'estimated_price' => 75.00, 'actual_price' => 75.00],
];

foreach ($items2 as $item) {
    LesbuyItem::create([
        'order_id' => $order2->id,
        'name' => $item['name'],
        'quantity' => $item['quantity'],
        'unit' => $item['unit'],
        'estimated_price' => $item['estimated_price'],
        'actual_price' => $item['actual_price'],
        'status' => 'completed',
    ]);
}

echo "✅ Order #{$order2->id} created with " . count($items2) . " items\n";

// Create LesBuy Order 3 - Pharmacy Items (Active Order)
echo "💊 Creating LesBuy Order 3 - Pharmacy Items (Active)...\n";
$order3 = Order::create([
    'customer_id' => $user->id,
    'service_id' => 4, // LesBuy
    'pickup_address' => 'Mercury Drug, Colon Street, Cebu City',
    'pickup_lat' => 10.2966,
    'pickup_lng' => 123.9018,
    'pickup_contact_name' => $user->name,
    'pickup_contact_phone' => $user->phone_number,
    'dropoff_address' => 'University of San Carlos, Talamban Campus',
    'dropoff_lat' => 10.3521,
    'dropoff_lng' => 123.9132,
    'dropoff_contact_name' => $user->name,
    'dropoff_contact_phone' => $user->phone_number,
    'status' => 'accepted',
    'estimated_distance_m' => 8200,
    'estimated_fare' => 95.50,
    'payment_method' => 'cash',
    'payment_status' => 'pending',
    'accepted_at' => now()->subMinutes(15),
    'created_at' => now()->subMinutes(30),
    'updated_at' => now()->subMinutes(15),
    'meta' => [
        'order_value' => 320.00,
        'grand_total' => 415.50
    ]
]);

// Add items to Order 3
$items3 = [
    ['name' => 'Paracetamol 500mg', 'quantity' => 2, 'unit' => 'box', 'estimated_price' => 45.00, 'actual_price' => null],
    ['name' => 'Vitamin C 1000mg', 'quantity' => 1, 'unit' => 'bottle', 'estimated_price' => 180.00, 'actual_price' => null],
    ['name' => 'Face Mask Surgical', 'quantity' => 1, 'unit' => 'box', 'estimated_price' => 95.00, 'actual_price' => null],
];

foreach ($items3 as $item) {
    LesbuyItem::create([
        'order_id' => $order3->id,
        'name' => $item['name'],
        'quantity' => $item['quantity'],
        'unit' => $item['unit'],
        'estimated_price' => $item['estimated_price'],
        'actual_price' => $item['actual_price'],
        'status' => 'pending',
    ]);
}

echo "✅ Order #{$order3->id} created with " . count($items3) . " items (Active)\n";

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    ORDERS CREATED                              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "📊 Summary:\n";
echo "   - Order #{$order1->id}: Grocery Shopping (7 items) - Completed\n";
echo "   - Order #{$order2->id}: Electronics Shopping (4 items) - Completed\n";
echo "   - Order #{$order3->id}: Pharmacy Items (3 items) - Active\n";
echo "\n";
echo "🎯 Now you can test the transaction display:\n";
echo "   1. Login with: carlkelvinmanahan014@gmail.com / password123\n";
echo "   2. Go to Transactions screen\n";
echo "   3. You should see LesBuy orders with item names and prices\n";
echo "\n";
echo "✅ Ready for testing!\n";
echo "\n";