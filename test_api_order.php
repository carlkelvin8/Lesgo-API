<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = App\Models\Order::with(['driverProfile.user', 'customer', 'orderItems', 'service', 'partner'])->latest()->first();
echo json_encode($order ? $order->toArray() : ['error' => 'No order found'], JSON_PRETTY_PRINT);
