<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\OrderController;
use App\Http\Requests\FilterOrderRequest;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Orders API Response Format\n\n";

$user = User::where('email', 'testcustomer@lesgo.com')->first();
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User: {$user->name} (ID: {$user->id})\n\n";

// Create a mock request
$request = new class extends FilterOrderRequest {
    public function validated($key = null, $default = null) {
        return ['per_page' => 100];
    }
    
    public function user($guard = null) {
        return User::find(923);
    }
};

// Create controller and call the method
$controller = new OrderController(app(\App\Services\RealtimeService::class));

try {
    $response = $controller->index($request);
    $responseData = $response->getData(true);
    
    echo "🟢 Response Status: {$response->getStatusCode()}\n";
    echo "🟢 Response Type: " . gettype($responseData) . "\n";
    echo "🟢 Response Keys: " . implode(', ', array_keys($responseData)) . "\n";
    echo "🟢 Success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
    echo "🟢 Data Count: " . count($responseData['data']) . "\n";
    
    if (!empty($responseData['data'])) {
        $first = $responseData['data'][0];
        echo "🟢 First Order ID: {$first['id']}\n";
        echo "🟢 First Order Service: {$first['service_id']}\n";
        echo "🟢 First Order Items: " . (isset($first['lesbuy_items']) ? count($first['lesbuy_items']) : 0) . "\n";
        
        if (isset($first['lesbuy_items']) && !empty($first['lesbuy_items'])) {
            $firstItem = $first['lesbuy_items'][0];
            echo "🟢 First Item Name: {$firstItem['name']}\n";
            echo "🟢 First Item Price: " . ($firstItem['actual_price'] ?? $firstItem['estimated_price'] ?? 'null') . "\n";
        }
    }
    
    // Check JSON encoding
    $json = json_encode($responseData);
    if ($json === false) {
        echo "❌ JSON encoding failed: " . json_last_error_msg() . "\n";
    } else {
        echo "🟢 JSON encoding successful\n";
        echo "🟢 JSON length: " . strlen($json) . " bytes\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "❌ Stack trace: " . $e->getTraceAsString() . "\n";
}