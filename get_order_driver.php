<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$order = DB::table('orders')->where('id', 412)->first();
if ($order) {
    echo "Order 412 driver_id: {$order->driver_id}\n";
} else {
    echo "Order 412 not found\n";
}
