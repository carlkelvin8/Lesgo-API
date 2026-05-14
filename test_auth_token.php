<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔵 Testing Authentication Token...\n";
    
    // Simulate a request with Bearer token
    $request = new \Illuminate\Http\Request();
    $request->headers->set('Authorization', 'Bearer 1|test-token');
    $request->headers->set('Accept', 'application/json');
    
    // Test the missions endpoint
    $response = \Illuminate\Support\Facades\Route::dispatch($request->create(
        'http://localhost:8000/api/v1/missions',
        'GET',
        [],
        [],
        [],
        ['HTTP_AUTHORIZATION' => 'Bearer 1|test-token', 'HTTP_ACCEPT' => 'application/json']
    ));
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content: " . $response->getContent() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}