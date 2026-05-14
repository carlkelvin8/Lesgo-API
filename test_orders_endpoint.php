<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing Orders Endpoint Response...\n\n";

// Find a customer user and create a token
$user = User::where('role', 'customer')->first();
if (!$user) {
    echo "❌ No customer user found\n";
    exit(1);
}

// Create a personal access token for testing
$token = $user->createToken('test-token')->plainTextToken;

echo "👤 Testing for user: {$user->name} ({$user->email})\n";
echo "🔑 Created test token\n\n";

// Make HTTP request to the orders endpoint
$baseUrl = env('APP_URL', 'http://localhost:8000');
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

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['success']) && $data['success']) {
        $orders = $data['data']['data']; // Paginated response
        echo "✅ API returned " . count($orders) . " orders\n\n";
        
        foreach ($orders as $order) {
            echo "📦 Order #{$order['id']} - Service ID: {$order['service_id']}\n";
            echo "   Status: {$order['status']}\n";
            
            if (isset($order['lesbuy_items']) && !empty($order['lesbuy_items'])) {
                echo "   ✅ LesBuy Items: " . count($order['lesbuy_items']) . "\n";
                foreach ($order['lesbuy_items'] as $item) {
                    $price = $item['actual_price'] ?? $item['estimated_price'] ?? 'N/A';
                    echo "      - {$item['name']} (qty: {$item['quantity']}, price: PHP {$price})\n";
                }
            } else {
                echo "   ❌ No LesBuy items found\n";
            }
            echo "\n";
        }
    } else {
        echo "❌ API response format error\n";
        echo "Response: " . $response . "\n";
    }
} else {
    echo "❌ HTTP request failed with code: $httpCode\n";
    echo "Response: " . $response . "\n";
}

// Clean up the test token
$user->tokens()->where('name', 'test-token')->delete();
echo "🧹 Cleaned up test token\n";
echo "✅ Test completed!\n";