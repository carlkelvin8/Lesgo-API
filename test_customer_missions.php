<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Api\CustomerMissionController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔵 Testing Customer Missions API...\n";
    
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
    $controller = new CustomerMissionController();
    
    // Test getting missions
    echo "\n🔵 Testing missions index...\n";
    $request = new Request();
    $response = $controller->index($request);
    
    $responseData = json_decode($response->getContent(), true);
    
    if ($responseData['success']) {
        echo "✅ Missions API working!\n";
        echo "📊 Found " . count($responseData['data']) . " missions:\n";
        
        foreach ($responseData['data'] as $mission) {
            echo "  - {$mission['title']}: {$mission['current_progress']}/{$mission['goal_target']} (" . 
                 round($mission['current_progress'] / $mission['goal_target'] * 100, 1) . "%)\n";
        }
    } else {
        echo "❌ API Error: " . $responseData['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}