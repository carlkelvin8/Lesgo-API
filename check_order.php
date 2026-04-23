<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;

$order = Order::find(25);

if (!$order) {
    echo "❌ Order #25 not found!\n";
    exit(1);
}

echo "Order #25 Details:\n";
echo "  Status: {$order->status}\n";
echo "  Driver ID: " . ($order->driver_id ?? 'NULL') . "\n";
echo "  Accepted At: " . ($order->accepted_at ?? 'NULL') . "\n";
echo "  Updated At: {$order->updated_at}\n";
