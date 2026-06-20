<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CREATING TEST ORDER ===\n\n";

$customer = App\Models\User::where('email', 'customer@lesgo.test')->first();
$service = App\Models\Service::where('code', 'LESGO')->first();

if (!$customer) {
    echo "❌ Customer not found!\n";
    exit(1);
}

if (!$service) {
    echo "❌ Service not found!\n";
    exit(1);
}

$order = App\Models\Order::create([
    'customer_id' => $customer->id,
    'service_id' => $service->id,
    'pickup_address' => '123 Test Street, Manila',
    'pickup_lat' => 14.5995,
    'pickup_lng' => 120.9842,
    'pickup_contact_name' => $customer->name,
    'pickup_contact_phone' => $customer->phone_number ?? '09123456789',
    'dropoff_address' => '456 Delivery Ave, Quezon City',
    'dropoff_lat' => 14.6488,
    'dropoff_lng' => 121.0509,
    'dropoff_contact_name' => 'Recipient Name',
    'dropoff_contact_phone' => '09987654321',
    'status' => 'pending',
    'payment_method' => 'cash',
    'payment_status' => 'pending',
    'estimated_distance_m' => 8500,
    'estimated_fare' => 120.00,
    'fare_breakdown' => [
        'base_fare' => 40.00,
        'distance_fare' => 80.00,
        'total' => 120.00,
    ],
    'notes' => 'Test order for rider acceptance',
]);

echo "✅ Order created successfully!\n\n";
echo "Order ID: {$order->id}\n";
echo "Customer: {$customer->name}\n";
echo "Service: {$service->name}\n";
echo "Pickup: {$order->pickup_address}\n";
echo "Dropoff: {$order->dropoff_address}\n";
echo "Fare: ₱{$order->estimated_fare}\n";
echo "Status: {$order->status}\n";
echo "\nThe rider can now accept this order in the app!\n";
