<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Api\ChatController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔵 Testing Chat Conversations API...\n";
    
    // Find test customer
    $user = User::where('email', 'testcustomer@lesgo.com')->first();
    
    if (!$user) {
        echo "❌ Test customer not found\n";
        exit(1);
    }
    
    echo "✅ Found test customer: {$user->name} (ID: {$user->id})\n";
    
    // Simulate authentication
    Auth::login($user);
    
    // Create controller instance
    $controller = new ChatController(app(\App\Services\RealtimeService::class));
    
    // Test getting conversations
    echo "\n🔵 Testing conversations endpoint...\n";
    $request = new Request();
    $response = $controller->conversations($request);
    
    $responseData = json_decode($response->getContent(), true);
    
    if ($responseData['success']) {
        echo "✅ Conversations API working!\n";
        echo "📊 Found " . count($responseData['data']['data']) . " conversations:\n";
        
        foreach ($responseData['data']['data'] as $conversation) {
            echo "  - Conversation ID: {$conversation['id']}\n";
            echo "    Order ID: {$conversation['order_id']}\n";
            echo "    Customer: " . ($conversation['customer']['name'] ?? 'N/A') . "\n";
            echo "    Driver: " . ($conversation['driver']['name'] ?? 'N/A') . "\n";
            echo "    Status: {$conversation['status']}\n";
            echo "    Unread Count: {$conversation['unread_count']}\n";
            echo "    ---\n";
        }
    } else {
        echo "❌ API Error: " . $responseData['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}