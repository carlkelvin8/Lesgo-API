<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "\n=== TEST ACCOUNTS ===\n\n";

echo "📱 CUSTOMER:\n";
$customer = User::where('email', 'testcustomer@lesgo.com')->first();
echo "  Email: testcustomer@lesgo.com\n";
echo "  Password: password\n";
echo "  User ID: {$customer->id}\n\n";

echo "🏍️  RIDER:\n";
$rider = User::where('email', 'testrider@lesgo.com')->with('driverProfile', 'wallet')->first();
echo "  Email: testrider@lesgo.com\n";
echo "  Password: password\n";
echo "  User ID: {$rider->id}\n";
echo "  Driver Profile ID: {$rider->driverProfile->id}\n";
echo "  Wallet Balance: ₱{$rider->wallet->balance}\n";
echo "  Status: {$rider->driverProfile->status}\n\n";

echo "✅ Both accounts are ready!\n\n";
