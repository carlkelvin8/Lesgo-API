<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test user ID
$userId = 923;

// Create a simple test image (1x1 red pixel PNG)
$testImageBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';

echo "Testing profile photo upload for user $userId\n";
echo "Base64 length: " . strlen($testImageBase64) . "\n\n";

// Simulate the updateProfile logic
$validated = ['profile_photo_url' => $testImageBase64];

if (isset($validated['profile_photo_url']) && str_starts_with($validated['profile_photo_url'], 'data:image')) {
    try {
        echo "Processing base64 image...\n";
        
        // Extract base64 data
        $image = $validated['profile_photo_url'];
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace('data:image/jpg;base64,', '', $image);
        $image = str_replace('data:image/jpeg;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $imageData = base64_decode($image);
        
        echo "Decoded image size: " . strlen($imageData) . " bytes\n";

        // Generate unique filename
        $filename = 'profile_pictures/' . $userId . '_' . time() . '.jpg';
        
        echo "Saving to: storage/app/public/$filename\n";
        
        // Store the image
        \Storage::disk('public')->put($filename, $imageData);
        
        echo "✅ Image saved successfully!\n";
        echo "File path: $filename\n";
        echo "Full URL: " . asset('storage/' . $filename) . "\n";
        
        // Update user record
        DB::table('users')
            ->where('id', $userId)
            ->update(['profile_photo_url' => $filename]);
        
        echo "✅ User record updated!\n";
        
        // Verify the file exists
        if (\Storage::disk('public')->exists($filename)) {
            echo "✅ File exists in storage!\n";
            $fileSize = \Storage::disk('public')->size($filename);
            echo "File size: $fileSize bytes\n";
        } else {
            echo "❌ File does not exist in storage!\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
} else {
    echo "❌ Invalid image format\n";
}

echo "\nDone!\n";
