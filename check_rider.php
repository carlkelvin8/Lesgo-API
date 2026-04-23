<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('email', 'testrider@lesgo.com')->first();

if (!$user) {
    echo "❌ User not found!\n";
    exit(1);
}

echo "✅ User found:\n";
echo "  ID: {$user->id}\n";
echo "  Name: {$user->name}\n";
echo "  Email: {$user->email}\n";
echo "  Role: {$user->role}\n";

$driver = $user->driverProfile;

if (!$driver) {
    echo "❌ Driver profile not found!\n";
    exit(1);
}

echo "\n✅ Driver Profile found:\n";
echo "  ID: {$driver->id}\n";
echo "  Status: {$driver->status}\n";
echo "  Rating: {$driver->rating}\n";
echo "  Vehicle Type: {$driver->vehicle_type}\n";
