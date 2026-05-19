<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$d = App\Models\DriverProfile::first();
if ($d) {
    $d->vehicle_type = 'Honda Click 125i';
    $d->plate_number = 'ABC 1234';
    $d->save();
    echo "Updated driver vehicle details\n";
} else {
    echo "No driver found\n";
}
