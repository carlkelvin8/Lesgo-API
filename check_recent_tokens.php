<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Checking Recent Tokens...\n\n";

// Get the most recent tokens
$recentTokens = PersonalAccessToken::with('tokenable')
    ->orderByDesc('id')
    ->take(10)
    ->get();

echo "📋 Most Recent Tokens:\n";
foreach ($recentTokens as $token) {
    $user = $token->tokenable;
    echo "   Token #{$token->id}: {$user->name} ({$user->email}) - {$user->role}\n";
    
    if ($user->role === 'customer') {
        $orderCount = $user->customerOrders()->count();
        echo "      Orders: {$orderCount}\n";
    }
}

// Let's also check if there are any tokens that start with "158|" in the token field
echo "\n🔍 Looking for tokens with hash starting with '9P7dIgt9BiLUmi0M'...\n";

// Since the token format is ID|hash, let's find tokens with that hash
$tokens = PersonalAccessToken::with('tokenable')
    ->where('token', 'like', '%9P7dIgt9BiLUmi0M%')
    ->get();

if ($tokens->isEmpty()) {
    echo "❌ No tokens found with that hash\n";
    
    // Let's try a different approach - find the user who has orders 40, 39, 38, etc.
    echo "\n🔍 Looking for user who has order #40...\n";
    $order40 = \App\Models\Order::find(40);
    if ($order40) {
        $user = $order40->customer;
        echo "✅ Order #40 belongs to: {$user->name} ({$user->email})\n";
        echo "   Role: {$user->role}\n";
        
        // Check their tokens
        $userTokens = $user->tokens()->orderByDesc('id')->take(3)->get();
        echo "   Recent tokens:\n";
        foreach ($userTokens as $token) {
            echo "      - Token #{$token->id} (created: {$token->created_at})\n";
        }
    } else {
        echo "❌ Order #40 not found\n";
    }
} else {
    foreach ($tokens as $token) {
        $user = $token->tokenable;
        echo "✅ Found token #{$token->id} for user: {$user->name} ({$user->email})\n";
    }
}

echo "\n✅ Search completed!\n";