<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing API endpoint directly\n\n";

$user = User::where('email', 'testcustomer@lesgo.com')->first();
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

// Create a token for this user
$token = $user->createToken('test-token')->plainTextToken;

echo "✅ User: {$user->name} (ID: {$user->id})\n";
echo "🔑 Token: {$token}\n\n";

// Test the API endpoint using curl
$url = 'http://127.0.0.1:8000/api/v1/orders?per_page=100';
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json',
];

echo "🔵 Testing API endpoint: {$url}\n";
echo "🔵 Headers: " . implode(', ', $headers) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: {$error}\n";
    exit(1);
}

echo "🟢 HTTP Status: {$httpCode}\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data) {
        echo "🟢 Response parsed successfully\n";
        echo "🟢 Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "🟢 Orders count: " . count($data['data']) . "\n";
        
        if (!empty($data['data'])) {
            $first = $data['data'][0];
            echo "🟢 First order ID: {$first['id']}\n";
            echo "🟢 First order service: {$first['service_id']}\n";
            echo "🟢 First order items: " . (isset($first['lesbuy_items']) ? count($first['lesbuy_items']) : 0) . "\n";
        }
    } else {
        echo "❌ Failed to parse JSON response\n";
        echo "Raw response: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "❌ HTTP Error: {$httpCode}\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}