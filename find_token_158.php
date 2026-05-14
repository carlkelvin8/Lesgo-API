<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Finding Token 158...\n\n";

// The token format is ID|hash, so 158 is the token ID
$token = PersonalAccessToken::with('tokenable')->find(158);

if (!$token) {
    echo "❌ Token 158 not found\n";
    
    // Let's check what tokens exist around that ID
    echo "📋 Tokens around ID 158:\n";
    $nearbyTokens = PersonalAccessToken::with('tokenable')
        ->whereBetween('id', [150, 170])
        ->orderBy('id')
        ->get();
        
    foreach ($nearbyTokens as $t) {
        $user = $t->tokenable;
        echo "   Token #{$t->id}: {$user->name} ({$user->email}) - {$user->role}\n";
    }
} else {
    $user = $token->tokenable;
    echo "✅ Found token #{$token->id} for user: {$user->name} ({$user->email})\n";
    echo "   Role: {$user->role}\n";
    echo "   Created: {$token->created_at}\n";
    
    // Check if this user has any orders
    $orderCount = $user->customerOrders()->count();
    echo "   Total Orders: {$orderCount}\n";
    
    if ($orderCount > 0) {
        $lesbuyCount = $user->customerOrders()->where('service_id', 4)->count();
        echo "   LesBuy Orders: {$lesbuyCount}\n";
        
        // Show recent orders
        $recentOrders = $user->customerOrders()->orderByDesc('id')->take(3)->get();
        echo "   Recent Orders:\n";
        foreach ($recentOrders as $order) {
            echo "      - Order #{$order->id} (Service: {$order->service_id})\n";
        }
    }
}

echo "\n✅ Search completed!\n";