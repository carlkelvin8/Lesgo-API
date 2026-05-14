<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing API for Test Customer...\n\n";

// Find the test customer user
$user = User::where('email', 'test@example.com')->first();
if (!$user) {
    echo "❌ Test customer not found\n";
    exit(1);
}

echo "👤 Testing for user: {$user->name} ({$user->email})\n";

// Create a personal access token for testing
$token = $user->createToken('test-token')->plainTextToken;

echo "🔑 Created test token\n\n";

// Make HTTP request to the orders endpoint
$baseUrl = 'http://localhost:8000';
$url = $baseUrl . '/api/v1/orders';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "🌐 HTTP Response Code: $httpCode\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ API Success: true\n";
        $orders = $data['data'];
        echo "📦 Total Orders: " . count($orders) . "\n\n";
        
        // Look for LesBuy orders specifically
        $lesbuyOrders = array_filter($orders, function($order) {
            return $order['service_id'] == 4;
        });
        
        echo "🛒 LesBuy Orders: " . count($lesbuyOrders) . "\n";
        
        foreach ($lesbuyOrders as $order) {
            echo "\n📦 LesBuy Order #{$order['id']}:\n";
            echo "   Service ID: {$order['service_id']}\n";
            echo "   Status: {$order['status']}\n";
            
            if (isset($order['lesbuy_items']) && !empty($order['lesbuy_items'])) {
                echo "   ✅ Items: " . count($order['lesbuy_items']) . "\n";
                foreach ($order['lesbuy_items'] as $item) {
                    echo "      - {$item['name']} (qty: {$item['quantity']}, price: PHP {$item['actual_price']})\n";
                }
            } else {
                echo "   ❌ No items found\n";
            }
        }
        
        if (empty($lesbuyOrders)) {
            echo "❌ No LesBuy orders found for this user\n";
            echo "📋 Available orders:\n";
            foreach (array_slice($orders, 0, 3) as $order) {
                echo "   - Order #{$order['id']} (Service ID: {$order['service_id']})\n";
            }
        }
        
    } else {
        echo "❌ API response format error\n";
        echo "Response: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "❌ HTTP request failed with code: $httpCode\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}

// Clean up the test token
$user->tokens()->where('name', 'test-token')->delete();
echo "\n🧹 Cleaned up test token\n";
echo "✅ Test completed!\n";