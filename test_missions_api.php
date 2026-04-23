<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find test rider
$user = \App\Models\User::where('email', 'testrider@lesgo.com')->first();

if (!$user) {
    echo "User not found\n";
    exit(1);
}

echo "Testing missions API for: {$user->name}\n";
echo "Driver Profile ID: {$user->driverProfile->id}\n\n";

// Create a mock request
$request = \Illuminate\Http\Request::create('/api/v1/driver/missions', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Call the controller
$controller = new \App\Http\Controllers\Api\DriverMissionController();
$response = $controller->index($request);

// Get response data
$data = json_decode($response->getContent(), true);

echo "API Response:\n";
echo json_encode($data, JSON_PRETTY_PRINT);
echo "\n";
