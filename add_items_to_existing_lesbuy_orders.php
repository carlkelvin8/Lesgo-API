<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Order;
use App\Models\LesbuyItem;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🛒 Adding Items to Existing LesBuy Orders...\n\n";

// Find LesBuy orders (service_id = 4) that don't have items
$lesbuyOrders = Order::where('service_id', 4)
    ->with(['customer', 'lesbuyItems'])
    ->get();

echo "📦 Found " . $lesbuyOrders->count() . " LesBuy orders\n\n";

foreach ($lesbuyOrders as $order) {
    $customer = $order->customer;
    echo "Order #{$order->id} - {$customer->name} ({$customer->email})\n";
    echo "   Current items: " . $order->lesbuyItems->count() . "\n";
    
    if ($order->lesbuyItems->count() === 0) {
        echo "   ➕ Adding test items...\n";
        
        // Create different items for each order
        $itemSets = [
            [
                ['name' => 'iPhone 15 Pro Max', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 75000.00, 'actual_price' => 72000.00],
                ['name' => 'AirPods Pro 2', 'quantity' => 1, 'unit' => 'pair', 'estimated_price' => 15000.00, 'actual_price' => 14500.00],
                ['name' => 'MagSafe Charger', 'quantity' => 2, 'unit' => 'piece', 'estimated_price' => 3000.00, 'actual_price' => 2800.00],
            ],
            [
                ['name' => 'Samsung Galaxy S24 Ultra', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 65000.00, 'actual_price' => 62000.00],
                ['name' => 'Galaxy Buds Pro', 'quantity' => 1, 'unit' => 'pair', 'estimated_price' => 12000.00, 'actual_price' => 11500.00],
                ['name' => 'Wireless Charger', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 2500.00, 'actual_price' => 2200.00],
            ],
            [
                ['name' => 'MacBook Air M3', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 85000.00, 'actual_price' => 82000.00],
                ['name' => 'Magic Mouse', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 5000.00, 'actual_price' => 4800.00],
                ['name' => 'USB-C Hub', 'quantity' => 1, 'unit' => 'piece', 'estimated_price' => 3500.00, 'actual_price' => 3200.00],
            ],
        ];
        
        $itemSet = $itemSets[($order->id - 1) % count($itemSets)];
        
        foreach ($itemSet as $itemData) {
            $item = LesbuyItem::create([
                'order_id' => $order->id,
                'name' => $itemData['name'],
                'quantity' => $itemData['quantity'],
                'unit' => $itemData['unit'],
                'estimated_price' => $itemData['estimated_price'],
                'actual_price' => $itemData['actual_price'],
            ]);
            
            echo "      ✅ {$item->name} (qty: {$item->quantity}, price: PHP {$item->actual_price})\n";
        }
    } else {
        echo "   ✅ Already has items\n";
    }
    echo "\n";
}

echo "🎉 Completed adding items to LesBuy orders!\n";
echo "✅ All LesBuy orders now have item details for testing.\n";