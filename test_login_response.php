<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::where('email', 'testrider@lesgo.com')->first();

if (!$user) {
    echo "User not found\n";
    exit;
}

echo "User found: {$user->name}\n";
echo "Role: {$user->role}\n";
echo "Is Driver: " . ($user->isDriver() ? 'Yes' : 'No') . "\n\n";

// Load driver profile
if ($user->isDriver()) {
    $user->load('driverProfile');
    echo "Driver Profile loaded\n";
    
    if ($user->driverProfile) {
        echo "Driver Profile ID: {$user->driverProfile->id}\n";
        echo "Vehicle Type: {$user->driverProfile->vehicle_type}\n";
        echo "Plate Number: {$user->driverProfile->plate_number}\n";
    } else {
        echo "Driver Profile is NULL\n";
    }
} else {
    echo "User is not a driver\n";
}

echo "\n\nFormatted Response:\n";
$formatted = $user->only([
    'id',
    'name',
    'email',
    'phone_number',
    'date_of_birth',
    'address_line1',
    'address_line2',
    'profile_photo_url',
    'profile_picture',
    'referral_code',
    'referred_by',
    'points',
    'role',
    'email_verified_at',
    'created_at',
    'updated_at',
]);

if ($user->driverProfile) {
    $formatted['driver_profile'] = $user->driverProfile->only([
        'id',
        'vehicle_type',
        'plate_number',
        'license_number',
        'status',
        'last_latitude',
        'last_longitude',
    ]);
}

echo json_encode($formatted, JSON_PRETTY_PRINT);
