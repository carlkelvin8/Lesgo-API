<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$drivers = DB::table('driver_profiles')
    ->join('users', 'driver_profiles.user_id', '=', 'users.id')
    ->select('users.id', 'users.name', 'users.email')
    ->limit(10)
    ->get();

echo "Drivers with profiles:\n";
foreach ($drivers as $driver) {
    echo "  ID: {$driver->id}, Name: {$driver->name}, Email: {$driver->email}\n";
}
