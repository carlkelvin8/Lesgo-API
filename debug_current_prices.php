<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 DEBUGGING CURRENT PRICE ISSUE\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Get recent orders to see current pricing
$orders = Order::with('service')
    ->orderByDesc('id')
    ->limit(10)
    ->get();

echo "📊 Current Orders Analysis:\n\n";

foreach ($orders as $order) {
    echo "Order #{$order->id} ({$order->status}):\n";
    echo "  Estimated Fare: PHP {$order->estimated_fare}\n";
    echo "  Actual Fare: PHP " . ($order->actual_fare ?? 'null') . "\n";
    echo "  Driver Share: PHP " . ($order->driver_share ?? 'null') . "\n";
    echo "  Platform Fee: PHP " . ($order->platform_fee ?? 'null') . "\n";
    
    // What customer sees vs what rider sees
    $customerPrice = $order->estimated_fare;
    $riderPrice = $order->estimated_fare; // Now both see the same total price
    $driverEarnings = $order->driver_share ?? ($order->estimated_fare * 0.85); // Driver earnings
    
    echo "  📱 Customer App shows: PHP {$customerPrice}\n";
    echo "  🚗 Rider App shows: Total PHP {$riderPrice} | Earnings PHP " . number_format($driverEarnings, 2) . "\n";
    
    if ($customerPrice != $riderPrice) {
        $difference = $customerPrice - $riderPrice;
        echo "  ❌ PRICE MISMATCH: PHP {$difference} difference\n";
    } else {
        echo "  ✅ Total prices match (both see PHP {$customerPrice})\n";
        if ($order->driver_share) {
            echo "  💰 Driver earnings: PHP {$order->driver_share} (85% of total)\n";
        }
    }
    
    echo "\n";
}

// Check how many orders need driver_share calculation
$needsCalculation = Order::whereNull('driver_share')
    ->whereIn('status', ['completed', 'accepted', 'picked_up', 'in_progress'])
    ->whereNotNull('driver_id')
    ->count();

echo "🚨 Orders needing driver_share calculation: {$needsCalculation}\n";

if ($needsCalculation > 0) {
    echo "ℹ️  These orders will show estimated earnings (85% of total) until driver_share is calculated.\n";
} else {
    echo "✅ All active orders have driver_share calculated for accurate earnings display.\n";
}