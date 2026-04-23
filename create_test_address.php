<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Address;

$user = User::find(923);
if ($user) {
    // Check if user already has addresses
    $existingCount = Address::where('user_id', 923)->count();
    echo "User has $existingCount existing addresses\n";
    
    if ($existingCount == 0) {
        $address = Address::create([
            'user_id' => 923,
            'label' => 'Home',
            'address_line1' => '123 Test Street, Makati City',
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'is_default' => true
        ]);
        echo "Created address ID: {$address->id}\n";
    }
    
    // List all addresses for this user
    $addresses = Address::where('user_id', 923)->get();
    echo "Addresses for user 923:\n";
    foreach ($addresses as $addr) {
        echo "- ID: {$addr->id}, Label: {$addr->label}, Address: {$addr->address_line1}\n";
    }
} else {
    echo "User not found\n";
}