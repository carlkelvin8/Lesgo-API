<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$driver = App\Models\DriverProfile::with('user')->first();
echo json_encode($driver ? $driver->toArray() : ['error' => 'No driver found'], JSON_PRETTY_PRINT);
