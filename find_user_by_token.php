
<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Finding User by Token...\n\n";

// Look for tokens that start with 158
$tokens = PersonalAccessToken::where('id', 158)->get();

if ($tokens->isEmpty()) {
    echo "❌ No token found with ID 158\n";
    
    // Show recent tokens
    echo "📋 Recent tokens:\n";
    $recentTokens = PersonalAccessToken::with('tokenable')->orderByDesc('id')->take(5)->get();
    foreach ($recentTokens as $token) {
        $user = $token->tokenable;
        echo "   Token #{$token->id}: {$user->name} ({$user->email})\n";
    }
} else {
    foreach ($tokens as $token) {
        $user = $token->tokenable;
        echo "✅ Found token #{$token->id} for user: {$user->name} ({$user->email})\n";
        echo "   Role: {$user->role}\n";
        echo "   Created: {$token->created_at}\n";
        
        // Check if this user has any orders
        $orderCount = $user->customerOrders()->count();
        echo "   Orders: {$orderCount}\n";
        
        if ($orderCount > 0) {
            $lesbuyCount = $user->customerOrders()->where('service_id', 4)->count();
            echo "   LesBuy Orders: {$lesbuyCount}\n";
        }
    }
}

echo "\n✅ Search completed!\n";