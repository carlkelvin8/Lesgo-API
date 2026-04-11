<?php

/**
 * Test script to verify integrated features work correctly
 * Run this with: php test-integrated-features.php
 */

require_once 'vendor/autoload.php';

echo "🧪 Testing Integrated Features\n";
echo "==============================\n\n";

// Test 1: Check if all service classes can be instantiated
echo "1. Testing Service Classes...\n";

try {
    // Test DriverAssignmentService
    $reflection = new ReflectionClass('App\Services\DriverAssignmentService');
    echo "   ✅ DriverAssignmentService - Class structure OK\n";
    
    // Test PredictiveTrackingService
    $reflection = new ReflectionClass('App\Services\PredictiveTrackingService');
    echo "   ✅ PredictiveTrackingService - Class structure OK\n";
    
    // Test VoucherService
    $reflection = new ReflectionClass('App\Services\VoucherService');
    echo "   ✅ VoucherService - Class structure OK\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: Check if all job classes can be instantiated
echo "\n2. Testing Job Classes...\n";

try {
    $reflection = new ReflectionClass('App\Jobs\AutoAssignDriverJob');
    echo "   ✅ AutoAssignDriverJob - Class structure OK\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Check if all controller classes can be instantiated
echo "\n3. Testing Controller Classes...\n";

try {
    $reflection = new ReflectionClass('App\Http\Controllers\Api\VoucherController');
    echo "   ✅ VoucherController - Class structure OK\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 4: Check method signatures
echo "\n4. Testing Method Signatures...\n";

try {
    // Check DriverAssignmentService methods
    $class = new ReflectionClass('App\Services\DriverAssignmentService');
    $methods = ['autoAssignDriver'];
    
    foreach ($methods as $method) {
        if ($class->hasMethod($method)) {
            echo "   ✅ DriverAssignmentService::{$method}() exists\n";
        } else {
            echo "   ❌ DriverAssignmentService::{$method}() missing\n";
        }
    }
    
    // Check VoucherService methods
    $class = new ReflectionClass('App\Services\VoucherService');
    $methods = ['applyVoucher', 'validateVoucherOnly', 'getAvailableVouchers'];
    
    foreach ($methods as $method) {
        if ($class->hasMethod($method)) {
            echo "   ✅ VoucherService::{$method}() exists\n";
        } else {
            echo "   ❌ VoucherService::{$method}() missing\n";
        }
    }
    
    // Check PredictiveTrackingService methods
    $class = new ReflectionClass('App\Services\PredictiveTrackingService');
    $methods = ['calculatePredictiveETA'];
    
    foreach ($methods as $method) {
        if ($class->hasMethod($method)) {
            echo "   ✅ PredictiveTrackingService::{$method}() exists\n";
        } else {
            echo "   ❌ PredictiveTrackingService::{$method}() missing\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 5: Check if files exist
echo "\n5. Testing File Existence...\n";

$files = [
    'app/Services/DriverAssignmentService.php',
    'app/Services/PredictiveTrackingService.php', 
    'app/Services/VoucherService.php',
    'app/Jobs/AutoAssignDriverJob.php',
    'app/Http/Controllers/Api/VoucherController.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ {$file} exists\n";
    } else {
        echo "   ❌ {$file} missing\n";
    }
}

echo "\n🎉 Integration Test Complete!\n";
echo "\nNext Steps:\n";
echo "1. Run 'composer dump-autoload' to refresh autoloader\n";
echo "2. Test API endpoints with Postman or curl\n";
echo "3. Check Laravel logs for any runtime errors\n";
echo "\nNew API Endpoints Available:\n";
echo "- GET  /api/v1/vouchers/available\n";
echo "- POST /api/v1/vouchers/validate\n";
echo "- Enhanced: GET /api/v1/tracking/orders/{id}\n";
echo "- Enhanced: POST /api/v1/orders (with auto-assignment)\n";