<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::whereHas('driverProfile', function($q) {
    $q->where('id', 74);
})->first();

if ($user) {
    echo "Driver 74 User:\n";
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Profile Picture: " . ($user->profile_picture ?? 'null') . "\n";
} else {
    echo "Driver 74 not found\n";
}
