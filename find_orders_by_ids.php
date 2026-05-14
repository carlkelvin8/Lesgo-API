<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Order;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Finding Orders by IDs...\n\n";

// The app is fetching orders 40, 39, 38, 37, 36, 35, 34, 13, 12
$orderIds = [40, 39, 38, 37, 36, 35, 34, 13, 12];

echo "📋 Looking for orders: " . implode(', ', $orderIds) . "\n\n";

$foundOrders = Order::whereIn('id', $orderIds)->with('customer')->get();

if ($foundOrders->isEmpty()) {
    echo "❌ None of these orders exist\n";
    
    // Let's see what orders do exist
    echo "\n📋 Recent orders:\n";
    $recentOrders = Order::with('customer')->orderByDesc('id')->take(10)->get();
    foreach ($recentOrders as $order) {
        $customer = $order->customer;
        echo "   Order #{$order->id}: {$customer->name} ({$customer->email}) - Service {$order->service_id}\n";
    }
} else {
    echo "✅ Found " . $foundOrders->count() . " orders:\n";
    
    $customerEmails = [];
    foreach ($foundOrders as $order) {
        $customer = $order->customer;
        echo "   Order #{$order->id}: {$customer->name} ({$customer->email}) - Service {$order->service_id}\n";
        $customerEmails[] = $customer->email;
    }
    
    // Find the most common customer
    $emailCounts = array_count_values($customerEmails);
    $mostCommonEmail = array_keys($emailCounts, max($emailCounts))[0];
    
    echo "\n👤 Most common customer: {$mostCommonEmail}\n";
    
    $user = User::where('email', $mostCommonEmail)->first();
    if ($user) {
        echo "   Name: {$user->name}\n";
        echo "   Role: {$user->role}\n";
        
        // Check if this user has LesBuy orders
        $lesbuyCount = $user->customerOrders()->where('service_id', 4)->count();
        echo "   LesBuy Orders: {$lesbuyCount}\n";
        
        if ($lesbuyCount === 0) {
            echo "\n🛒 Creating LesBuy order for this user...\n";
            // This user needs LesBuy test data!
        }
    }
}

echo "\n✅ Search completed!\n";