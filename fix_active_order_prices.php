<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 FIXING ACTIVE ORDER PRICES\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Find orders that need driver_share calculation
$ordersToFix = Order::whereNull('driver_share')
    ->whereIn('status', ['accepted', 'driver_arrived_at_pickup', 'in_progress', 'picked_up', 'completed'])
    ->whereNotNull('driver_id') // Only orders with assigned drivers
    ->with('service')
    ->get();

echo "📊 Found {$ordersToFix->count()} orders needing driver_share calculation\n\n";

if ($ordersToFix->isEmpty()) {
    echo "✅ No orders need fixing!\n";
    exit(0);
}

foreach ($ordersToFix as $order) {
    echo "Fixing Order #{$order->id} ({$order->status}):\n";
    
    // Use actual fare if available, otherwise use estimated fare
    $totalFare = $order->actual_fare ?? $order->estimated_fare;
    echo "  Total Fare: PHP {$totalFare}\n";
    
    // Platform takes 15% commission
    $platformCommissionRate = 0.15;
    $platformFee = round($totalFare * $platformCommissionRate, 2);
    $driverShare = round($totalFare - $platformFee, 2);
    
    echo "  Platform Fee (15%): PHP {$platformFee}\n";
    echo "  Driver Share (85%): PHP {$driverShare}\n";
    
    // Update the order
    $order->platform_fee = $platformFee;
    $order->driver_share = $driverShare;
    
    // If partner is involved, calculate partner share
    if ($order->partner_id && in_array($order->service?->code, ['LESBUY', 'LESEAT'])) {
        $partnerCommissionRate = 0.05; // 5% to partner
        $partnerShare = round($totalFare * $partnerCommissionRate, 2);
        $adjustedDriverShare = round($totalFare - $platformFee - $partnerShare, 2);
        
        $order->partner_share = $partnerShare;
        $order->driver_share = $adjustedDriverShare;
        
        echo "  Partner Share (5%): PHP {$partnerShare}\n";
        echo "  Adjusted Driver Share (80%): PHP {$adjustedDriverShare}\n";
    }
    
    $order->save();
    echo "  ✅ Updated!\n\n";
}

echo "🎉 All orders fixed! Riders should now see correct earnings.\n";
echo "\nPrice comparison:\n";
echo "- Customer sees: Full fare (estimated_fare)\n";
echo "- Rider sees: Driver earnings (driver_share = 85% of total)\n";
echo "- Platform keeps: 15% commission\n";