<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\DriverProfile;
use App\Models\Wallet;

$user = User::where('email', 'testrider@lesgo.com')->first();

if (!$user) {
    echo "❌ User not found!\n";
    exit(1);
}

echo "✅ User found (ID: {$user->id})\n";

// Create or update driver profile
$driver = $user->driverProfile;

if (!$driver) {
    echo "Creating driver profile...\n";
    $driver = DriverProfile::create([
        'user_id' => $user->id,
        'license_number' => 'N01-23-456789',
        'status' => 'active',
        'rating' => 5.0,
        'total_trips' => 0,
        'last_latitude' => 14.5995,
        'last_longitude' => 120.9842,
    ]);
    echo "✅ Driver profile created (ID: {$driver->id})\n";
} else {
    echo "✅ Driver profile exists (ID: {$driver->id})\n";
    if ($driver->status !== 'active') {
        $driver->status = 'active';
        $driver->save();
        echo "✅ Updated status to 'active'\n";
    }
}

// Create or check wallet
$wallet = $user->wallet;

if (!$wallet) {
    echo "Creating wallet...\n";
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 500.00,
        'currency' => 'PHP',
    ]);
    echo "✅ Wallet created (Balance: PHP {$wallet->balance})\n";
} else {
    echo "✅ Wallet exists (Balance: PHP {$wallet->balance})\n";
}

echo "\n🎉 Test rider account is ready!\n";
echo "   Email: testrider@lesgo.com\n";
echo "   Password: TestRider123!\n";
echo "   Driver Profile ID: {$driver->id}\n";
echo "   Status: {$driver->status}\n";
echo "   Wallet Balance: PHP {$wallet->balance}\n";
