<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\User;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Finding Test Users\n";
echo str_repeat('=', 60) . "\n\n";

// Get recent orders to see which users have orders
$recentOrders = Order::with('customer:id,name,email')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get();

echo "📦 Recent orders and their customers:\n";
foreach ($recentOrders as $order) {
    echo "   Order #{$order->id}: {$order->customer->name} ({$order->customer->email})\n";
}

echo "\n👥 All users with 'test' in email:\n";
$testUsers = User::where('email', 'like', '%test%')->get();
foreach ($testUsers as $user) {
    echo "   {$user->name} ({$user->email}) - ID: {$user->id}\n";
}

echo "\n✅ Search complete!\n";