<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = User::where('email', 'carlkelvinmanahan014@gmail.com')->first();
if (!$user) {
    echo "User not found\n";
    exit(1);
}

echo "Customer ID: {$user->id}\n";

$orders = Order::where('customer_id', $user->id)
    ->with('lesbuyItems')
    ->orderBy('id', 'desc')
    ->take(5)
    ->get();

echo "Found {$orders->count()} orders for this customer\n\n";

foreach ($orders as $order) {
    echo "Order #{$order->id} - Service: {$order->service_id} - Status: {$order->status}\n";
    echo "  Items: {$order->lesbuyItems->count()}\n";
    foreach ($order->lesbuyItems as $item) {
        echo "    - {$item->name} (qty: {$item->quantity}, est: {$item->estimated_price}, actual: {$item->actual_price})\n";
    }
    echo "\n";
}