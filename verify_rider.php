<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$user = User::where('email', 'testrider@lesgo.com')->first();

if (!$user) {
    echo "❌ NOT IN DATABASE\n";
    exit(1);
}

echo "✅ YES, NASA DATABASE NA!\n\n";
echo "═══════════════════════════════════════════\n";
echo "USER DETAILS:\n";
echo "═══════════════════════════════════════════\n";
echo "  ID: {$user->id}\n";
echo "  Name: {$user->name}\n";
echo "  Email: {$user->email}\n";
echo "  Phone: {$user->phone_number}\n";
echo "  Role: {$user->role}\n";
echo "  Email Verified: " . ($user->email_verified_at ? 'YES' : 'NO') . "\n";

$driver = $user->driverProfile;

if ($driver) {
    echo "\n═══════════════════════════════════════════\n";
    echo "DRIVER PROFILE:\n";
    echo "═══════════════════════════════════════════\n";
    echo "  Driver ID: {$driver->id}\n";
    echo "  Status: {$driver->status}\n";
    echo "  License: {$driver->license_number}\n";
    echo "  Rating: {$driver->rating}\n";
    echo "  Total Trips: {$driver->total_trips}\n";
    echo "  Location: {$driver->last_latitude}, {$driver->last_longitude}\n";
}

$wallet = $user->wallet;

if ($wallet) {
    echo "\n═══════════════════════════════════════════\n";
    echo "WALLET:\n";
    echo "═══════════════════════════════════════════\n";
    echo "  Balance: PHP {$wallet->balance}\n";
    echo "  Currency: {$wallet->currency}\n";
}

echo "\n═══════════════════════════════════════════\n";
echo "LOGIN CREDENTIALS:\n";
echo "═══════════════════════════════════════════\n";
echo "  Email: testrider@lesgo.com\n";
echo "  Password: TestRider123!\n";
echo "═══════════════════════════════════════════\n";
