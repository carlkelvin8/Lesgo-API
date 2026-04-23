<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find test rider user
$user = \App\Models\User::where('email', 'testrider@lesgo.com')->first();

if (!$user) {
    echo "Test rider not found!\n";
    exit(1);
}

echo "Found user: {$user->name} (ID: {$user->id})\n";

// Update user's address fields
$user->update([
    'address_line1' => '123 Rizal Street, Barangay San Antonio',
    'address_line2' => 'Makati City, Metro Manila',
]);

echo "✅ Updated user address fields\n";
echo "Address Line 1: {$user->address_line1}\n";
echo "Address Line 2: {$user->address_line2}\n";

echo "\nDone! ✅\n";
