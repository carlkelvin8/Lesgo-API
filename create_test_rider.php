<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\DriverProfile;
use App\Models\Wallet;
use App\Models\DriverLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

DB::transaction(function() {
    // Delete existing test rider if exists
    $existingUser = User::where('email', 'testrider@lesgo.com')->first();
    if ($existingUser) {
        DriverLocation::where('driver_id', $existingUser->id)->delete();
        if ($existingUser->driverProfile) {
            $existingUser->driverProfile->delete();
        }
        Wallet::where('user_id', $existingUser->id)->delete();
        $existingUser->delete();
        echo "Deleted existing test rider\n";
    }
    
    // Create test rider user
    $user = User::create([
        'name' => 'Test Rider',
        'email' => 'testrider@lesgo.com',
        'password' => Hash::make('password'),
        'role' => 'driver',
        'phone_number' => '+639171234567',
        'referral_code' => strtoupper(Str::random(8)),
        'points' => 0
    ]);
    
    // Create driver profile
    $profile = DriverProfile::create([
        'user_id' => $user->id,
        'license_number' => 'TEST-LICENSE-001',
        'status' => 'active',
        'rating' => 5.0,
        'total_trips' => 0
    ]);
    
    // Create wallet with sufficient balance
    Wallet::create([
        'user_id' => $user->id,
        'balance' => 1000.00,
        'currency' => 'PHP'
    ]);
    
    // Create initial location (Manila area)
    DriverLocation::create([
        'driver_id' => $user->id,
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'heading' => 0,
        'speed' => 0,
        'accuracy' => 10,
        'status' => 'online',
        'recorded_at' => now()
    ]);
    
    echo "\n✅ Test Rider Created Successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "User ID: {$user->id}\n";
    echo "Driver Profile ID: {$profile->id}\n";
    echo "Email: testrider@lesgo.com\n";
    echo "Password: password\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
});
