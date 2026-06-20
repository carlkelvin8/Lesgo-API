<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DRIVER WALLET CHECK ===\n\n";

$driver = App\Models\User::where('email', 'driver@lesgo.test')->first();

if (!$driver) {
    echo "Driver not found!\n";
    exit(1);
}

echo "Driver: {$driver->name} (ID: {$driver->id})\n";
echo "Email: {$driver->email}\n";
echo "Phone: {$driver->phone_number}\n";
echo "Role: {$driver->role}\n";

$wallet = $driver->wallet;

if (!$wallet) {
    echo "\n❌ No wallet found! Creating wallet...\n";
    $wallet = App\Models\Wallet::create([
        'user_id' => $driver->id,
        'balance' => 1000.00,
        'currency' => 'PHP',
    ]);
    echo "✅ Wallet created with ₱1000.00\n";
} else {
    echo "\nWallet Balance: ₱{$wallet->balance}\n";
    echo "Currency: {$wallet->currency}\n";
    
    if ($wallet->balance < 50) {
        echo "\n⚠️ Insufficient balance for accepting orders!\n";
        echo "Adding ₱1000 to wallet...\n";
        $wallet->balance += 1000;
        $wallet->save();
        echo "✅ New balance: ₱{$wallet->balance}\n";
    }
}

$driverProfile = $driver->driverProfile;

if (!$driverProfile) {
    echo "\n❌ No driver profile found!\n";
} else {
    echo "\nDriver Profile ID: {$driverProfile->id}\n";
    echo "Status: {$driverProfile->status}\n";
    echo "Vehicle: {$driverProfile->vehicle_type}\n";
    echo "Plate: {$driverProfile->plate_number}\n";
}
