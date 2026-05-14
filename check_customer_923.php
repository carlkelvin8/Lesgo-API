<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = User::find(923);
if ($user) {
    echo "Customer ID 923:\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Phone: {$user->phone_number}\n";
    echo "Role: {$user->role}\n";
    echo "Created: {$user->created_at}\n";
} else {
    echo "Customer ID 923 not found\n";
}