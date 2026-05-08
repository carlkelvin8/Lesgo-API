<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

echo "🔍 Testing Login API Response...\n\n";

// Create a test request
$request = \Illuminate\Http\Request::create(
    '/api/v1/auth/login',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json'],
    json_encode([
        'email' => 'merchant@test.com',
        'password' => 'password123',
        'device_name' => 'test-device',
    ])
);

$request->headers->set('Accept', 'application/json');

try {
    $response = $kernel->handle($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    echo "✅ Login API Response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    
    if (isset($data['user']['partner_id'])) {
        echo "✅ partner_id is present: " . $data['user']['partner_id'] . "\n";
    } else {
        echo "❌ partner_id is MISSING from response!\n";
        echo "   User data keys: " . implode(', ', array_keys($data['user'] ?? [])) . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
