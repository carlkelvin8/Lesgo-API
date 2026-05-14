<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = User::find(923);
if ($user) {
    $password = 'password123';
    $user->password = Hash::make($password);
    $user->save();
    
    echo "✅ Password updated for customer ID 923\n";
    echo "Email: {$user->email}\n";
    echo "Password: {$password}\n";
    echo "\nThis customer has orders with items:\n";
    echo "- LesBuy orders with snacks, food items\n";
    echo "- LesEat orders with pizza, pasta\n";
} else {
    echo "Customer not found\n";
}