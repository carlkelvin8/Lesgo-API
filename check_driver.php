<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find drivers
$drivers = DB::table('users')->where('role', 'driver')->get(['id', 'name', 'email']);

echo "Available Drivers:\n";
foreach ($drivers as $driver) {
    echo "  ID: {$driver->id}, Name: {$driver->name}, Email: {$driver->email}\n";
}

// Find the driver from the logs (ID 74 or any driver)
$testDriver = DB::table('users')->where('role', 'driver')->first();
if ($testDriver) {
    echo "\nUsing driver ID: {$testDriver->id}\n";
} else {
    echo "\nNo drivers found!\n";
}
