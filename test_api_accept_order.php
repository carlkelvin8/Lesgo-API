<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get token for driver@lesgo.test
$driver = \App\Models\User::where('email', 'driver@lesgo.test')->first();

if (!$driver) {
    echo "Driver not found!\n";
    exit(1);
}

$token = $driver->tokens()->first();

if (!$token) {
    echo "No token found for driver!\n";
    exit(1);
}

echo "=== SIMULATING API CALL ===\n\n";
echo "Driver: {$driver->name} (ID: {$driver->id})\n";
echo "Token: {$token->token}\n\n";

// Create a request object
$request = Request::create(
    '/api/v1/orders/2/status',
    'PATCH',
    ['status' => 'accepted'],
    [],
    [],
    [
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token->token,
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ],
    json_encode(['status' => 'accepted'])
);

$request->headers->set('Authorization', 'Bearer ' . $token->token);
$request->headers->set('Accept', 'application/json');

// Set the authenticated user
$request->setUserResolver(function () use ($driver) {
    return $driver;
});

try {
    $controller = new \App\Http\Controllers\Api\OrderController();
    $order = \App\Models\Order::find(2);
    
    if (!$order) {
        echo "Order #2 not found!\n";
        exit(1);
    }
    
    echo "Order #2 found: Status={$order->status}, Fare={$order->estimated_fare}\n\n";
    
    // Create a validated request
    $validationRequest = \App\Http\Requests\Order\UpdateOrderStatusRequest::createFrom($request);
    $validationRequest->setUserResolver($request->getUserResolver());
    $validationRequest->setRouteResolver($request->getRouteResolver());
    
    // Manually validate
    $validator = \Illuminate\Support\Facades\Validator::make(
        ['status' => 'accepted'],
        $validationRequest->rules()
    );
    
    if ($validator->fails()) {
        echo "❌ Validation failed:\n";
        print_r($validator->errors()->all());
        exit(1);
    }
    
    echo "✅ Validation passed\n";
    echo "Attempting to accept order...\n\n";
    
    $response = $controller->updateStatus($validationRequest, $order);
    
    echo "Response Status: {$response->status()}\n";
    echo "Response Data:\n";
    print_r(json_decode($response->getContent(), true));
    
} catch (\Throwable $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}
