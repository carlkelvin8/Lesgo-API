<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Partner;

echo "🔗 Linking Merchant to Partner...\n\n";

$user = User::where('email', 'merchant@test.com')->first();

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ Found user: {$user->email} (ID: {$user->id})\n";

$partner = Partner::where('slug', 'test-restaurant')->first();

if (!$partner) {
    echo "❌ Partner not found\n";
    exit(1);
}

echo "✅ Found partner: {$partner->name} (ID: {$partner->id})\n";

$partner->user_id = $user->id;
$partner->save();

echo "\n🎉 Successfully linked!\n";
echo "   User ID: {$user->id}\n";
echo "   Partner ID: {$partner->id}\n";
echo "   Partner Name: {$partner->name}\n\n";

// Verify
$user->load('partner');
if ($user->partner) {
    echo "✅ Verification: User now has partner_id = {$user->partner->id}\n";
} else {
    echo "❌ Verification failed\n";
}
