<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing Actual API Endpoint...\n\n";

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
        
        // Check if data is paginated
        if (isset($data['data']['data'])) {
            echo "📄 Paginated response detected\n";
            $orders = $data['data']['data'];
            echo "📦 Orders in paginated data: " . count($orders) . "\n";
        } else {
            echo "📋 Direct array response\n";
            $orders = $data['data'];
            echo "📦 Orders in direct data: " . count($orders) . "\n";
        }
        
        // Check first order for lesbuy_items
        if (!empty($orders)) {
            $firstOrder = $orders[0];
            echo "\n🔍 First Order Analysis:\n";
            echo "   ID: {$firstOrder['id']}\n";
            echo "   Service ID: {$firstOrder['service_id']}\n";
            
            if (isset($firstOrder['lesbuy_items'])) {
                echo "   ✅ Has lesbuy_items field: " . count($firstOrder['lesbuy_items']) . " items\n";
                if (!empty($firstOrder['lesbuy_items'])) {
                    $item = $firstOrder['lesbuy_items'][0];
                    echo "      Sample item: {$item['name']} - PHP {$item['actual_price']}\n";
                }
            } else {
                echo "   ❌ No lesbuy_items field\n";
            }
        }
        
        // Show raw response structure
        echo "\n📋 Response Structure:\n";
        echo "   Keys: " . implode(', ', array_keys($data)) . "\n";
        if (isset($data['data'])) {
            if (is_array($data['data']) && !empty($data['data'])) {
                if (isset($data['data']['data'])) {
                    echo "   data.data keys: " . implode(', ', array_keys($data['data'])) . "\n";
                } else {
                    echo "   data[0] keys: " . implode(', ', array_keys($data['data'][0])) . "\n";
                }
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