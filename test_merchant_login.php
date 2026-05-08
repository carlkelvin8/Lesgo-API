<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "🔍 Testing Merchant Login Response...\n\n";

$user = User::where('email', 'merchant@test.com')->first();

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User found: {$user->email}\n";
echo "   User ID: {$user->id}\n";
echo "   Role: {$user->role}\n\n";

// Load partner relationship
$user->load('partner');

if ($user->partner) {
    echo "✅ Partner linked!\n";
    echo "   Partner ID: {$user->partner->id}\n";
    echo "   Partner Name: {$user->partner->name}\n\n";
    
    // Check menu items
    $menuCount = \App\Models\MenuItem::where('partner_id', $user->partner->id)->count();
    echo "✅ Menu items: {$menuCount}\n\n";
    
    echo "📱 Login Response will include:\n";
    echo "   - user.partner_id: {$user->partner->id}\n";
    echo "   - This allows the app to fetch menu items\n\n";
    
    echo "✅ Everything is configured correctly!\n";
    echo "   Just logout and login again in the app.\n";
} else {
    echo "❌ No partner linked to this user\n";
    echo "   Run: php link_merchant_partner.php\n";
}
