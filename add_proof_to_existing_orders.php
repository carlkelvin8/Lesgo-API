<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find completed orders without proof images for driver 74
$orders = DB::table('orders')
    ->where('driver_id', 74)
    ->where('status', 'completed')
    ->whereNull('proof_images')
    ->limit(3)
    ->get();

echo "Found " . count($orders) . " completed orders without proof images for driver 74\n\n";

$proofSets = [
    [
        'proof_images/412/VsFF5uzJcWPTeF4lrOLRlZxVASbmE9spiRosXUqF.jpg',
        'proof_images/412/6K2MSiEpmj4r82b4VotA560Ph2SRCeFGKfVhQGjc.jpg',
    ],
    [
        'proof_images/412/t848ci0MQUWiI4N9luugLHi2MNCYHzsiv9uEI4e1.png',
        'proof_images/412/lpSAtYU953b6CZALyxbSIKSvjpRtVXlMbH4cv74i.jpg',
    ],
    [
        'proof_images/404/jUmiogk8cibq21PhKXQB92CpjBL8SN4GxCm5YwDc.jpg',
        'proof_images/404/9cbdIIk1XDlL9fQ0eGknBiJymo2KanSyMOQKbKMw.jpg',
        'proof_images/404/SP0MY35eCbA1nj3Vpj5DNn8ZRWxuR5oFWDEadVZZ.jpg',
    ],
];

$index = 0;
foreach ($orders as $order) {
    $proofImages = $proofSets[$index % count($proofSets)];
    
    DB::table('orders')
        ->where('id', $order->id)
        ->update(['proof_images' => json_encode($proofImages)]);
    
    echo "✅ Added " . count($proofImages) . " proof images to order #{$order->id}\n";
    $index++;
}

echo "\n🎉 Done! Added proof images to " . count($orders) . " orders\n";
