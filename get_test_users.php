<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get all customer users
$customers = DB::table('users')
    ->where('role', 'customer')
    ->select('id', 'name', 'email', 'role', 'phone_number')
    ->get();

echo "=== CUSTOMER ACCOUNTS ===\n\n";
foreach ($customers as $user) {
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Phone: {$user->phone_number}\n";
    echo "Password: password123 (default for test accounts)\n";
    echo "---\n\n";
}

// Get all driver users
$drivers = DB::table('users')
    ->where('role', 'driver')
    ->select('id', 'name', 'email', 'role', 'phone_number')
    ->get();

echo "\n=== DRIVER ACCOUNTS ===\n\n";
foreach ($drivers as $user) {
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Phone: {$user->phone_number}\n";
    echo "Password: password123 (default for test accounts)\n";
    echo "---\n\n";
}

echo "\nTotal Customers: " . count($customers) . "\n";
echo "Total Drivers: " . count($drivers) . "\n";
