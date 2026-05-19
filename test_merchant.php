<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'merchant@test.com')->first();
echo json_encode([
    'isPartnerAdmin' => $user ? $user->isPartnerAdmin() : false, 
    'partner_id' => $user && $user->partner ? $user->partner->id : null
]);
