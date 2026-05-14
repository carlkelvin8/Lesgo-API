<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\LesbuyItem;
use Illuminate\Support\Facades\DB;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking LesBuy Items in Production Database\n";
echo str_repeat('=', 60) . "\n\n";

// Check total counts
$totalOrders = Order::count();
$totalItems = LesbuyItem::count();

echo "📊 Database Summary:\n";
echo "   Total Orders: {$totalOrders}\n";
echo "   Total LesbuyItems: {$totalItems}\n\n";

// Check recent orders
echo "📦 Recent Orders (last 5):\n";
$recentOrders = Order::with(['lesbuyItems', 'customer:id,name,email'])
    ->orderBy('id', 'desc')
    ->take(5)
    ->get();

foreach ($recentOrders as $order) {
    echo "   Order #{$order->id} (Service: {$order->service_id})\n";
    echo "      Customer: {$order->customer->name} ({$order->customer->email})\n";
    echo "      Status: {$order->status}\n";
    echo "      Created: {$order->created_at}\n";
    echo "      Items: {$order->lesbuyItems->count()}\n";
    
    if ($order->lesbuyItems->count() > 0) {
        foreach ($order->lesbuyItems as $item) {
            $price = $item->actual_price ?? $item->estimated_price ?? 'null';
            echo "         - {$item->name} (qty: {$item->quantity}, price: {$price})\n";
        }
    } else {
        echo "         ❌ No items found\n";
    }
    echo "\n";
}

// Check if there are any LesbuyItems at all
echo "🔍 All LesbuyItems in database:\n";
$allItems = LesbuyItem::with('order:id,service_id,status')->orderBy('id', 'desc')->take(10)->get();

if ($allItems->count() > 0) {
    foreach ($allItems as $item) {
        echo "   Item #{$item->id}: {$item->name} (Order #{$item->order_id})\n";
        echo "      Quantity: {$item->quantity}, Price: " . ($item->actual_price ?? $item->estimated_price ?? 'null') . "\n";
        if ($item->order) {
            echo "      Order Service: {$item->order->service_id}, Status: {$item->order->status}\n";
        }
        echo "\n";
    }
} else {
    echo "   ❌ No LesbuyItems found in database!\n\n";
    
    // Check if the lesbuy_items table exists
    echo "🔍 Checking if lesbuy_items table exists:\n";
    try {
        $tableExists = DB::select("SHOW TABLES LIKE 'lesbuy_items'");
        if (empty($tableExists)) {
            echo "   ❌ lesbuy_items table does not exist!\n";
        } else {
            echo "   ✅ lesbuy_items table exists\n";
            
            // Check table structure
            $columns = DB::select("DESCRIBE lesbuy_items");
            echo "   📋 Table structure:\n";
            foreach ($columns as $column) {
                echo "      - {$column->Field} ({$column->Type})\n";
            }
        }
    } catch (Exception $e) {
        echo "   ❌ Error checking table: {$e->getMessage()}\n";
    }
}

echo "\n✅ Check complete!\n";