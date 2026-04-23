<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\TwilioVerifyService;
use Illuminate\Support\Facades\Config;

// Load Laravel configuration
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test phone number (replace with your actual phone number)
$testPhoneNumber = '+639498787542'; // Your verified Twilio phone number

echo "🔵 Testing Twilio Verify Service Integration\n";
echo "============================================\n\n";

try {
    $twilioService = new TwilioVerifyService();
    
    echo "📱 Test Phone Number: " . $testPhoneNumber . "\n";
    echo "🔑 Verify Service SID: " . config('services.twilio.verify_service_sid') . "\n\n";
    
    // Test 1: Send verification
    echo "🚀 Step 1: Sending verification code...\n";
    $sendResult = $twilioService->sendVerification($testPhoneNumber);
    
    if ($sendResult['success']) {
        echo "✅ Verification sent successfully!\n";
        echo "   Status: " . $sendResult['status'] . "\n";
        echo "   Message: " . $sendResult['message'] . "\n";
        echo "   SID: " . ($sendResult['sid'] ?? 'N/A') . "\n\n";
        
        // Prompt for OTP code
        echo "📥 Please enter the verification code you received: ";
        $handle = fopen("php://stdin", "r");
        $otpCode = trim(fgets($handle));
        fclose($handle);
        
        if (!empty($otpCode)) {
            // Test 2: Verify code
            echo "\n🔍 Step 2: Verifying code '$otpCode'...\n";
            $verifyResult = $twilioService->verifyCode($testPhoneNumber, $otpCode);
            
            if ($verifyResult['success'] && $verifyResult['valid']) {
                echo "✅ Verification successful!\n";
                echo "   Status: " . $verifyResult['status'] . "\n";
                echo "   Message: " . $verifyResult['message'] . "\n";
                echo "   SID: " . ($verifyResult['sid'] ?? 'N/A') . "\n";
            } else {
                echo "❌ Verification failed!\n";
                echo "   Status: " . ($verifyResult['status'] ?? 'unknown') . "\n";
                echo "   Message: " . $verifyResult['message'] . "\n";
                echo "   Error: " . ($verifyResult['error'] ?? 'N/A') . "\n";
            }
        } else {
            echo "⚠️  No OTP code entered, skipping verification test.\n";
        }
        
    } else {
        echo "❌ Failed to send verification!\n";
        echo "   Message: " . $sendResult['message'] . "\n";
        echo "   Error: " . ($sendResult['error'] ?? 'N/A') . "\n";
    }
    
} catch (Exception $e) {
    echo "💥 Exception occurred: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n🏁 Test completed!\n";
echo "\nNext steps:\n";
echo "1. Make sure your Twilio credentials are set in .env\n";
echo "2. Ensure the Verify Service SID is correct\n";
echo "3. Test with the frontend registration flow\n";