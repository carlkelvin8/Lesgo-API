<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 MAKING CUSTOMER AND RIDER PRICES THE SAME\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Find all orders that have driver_share calculated
$ordersToReset = Order::whereNotNull('driver_share')->get();

echo "📊 Found {$ordersToReset->count()} orders with commission calculations\n\n";

if ($ordersToReset->isEmpty()) {
    echo "✅ No orders need resetting - prices already match!\n";
    exit(0);
}

foreach ($ordersToReset as $order) {
    echo "Resetting Order #{$order->id} ({$order->status}):\n";
    echo "  Before: Customer sees PHP {$order->estimated_fare}, Rider sees PHP {$order->driver_share}\n";
    
    // Remove commission calculations
    $order->driver_share = null;
    $order->platform_fee = null;
    $order->partner_share = null;
    $order->save();
    
    echo "  After: Both Customer and Rider see PHP {$order->estimated_fare}\n";
    echo "  ✅ Fixed!\n\n";
}

echo "🎉 All orders fixed! Both customer and rider now see the same price.\n";
echo "\nPrice display:\n";
echo "- Customer sees: Full fare (estimated_fare)\n";
echo "- Rider sees: Same full fare (estimated_fare)\n";
echo "- No commission deduction shown to users\n";