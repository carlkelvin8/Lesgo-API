<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'merchant@test.com')->first();
if ($user) {
    echo "User found: " . $user->id . "\n";
    $partner = App\Models\Partner::where('user_id', $user->id)->first();
    if ($partner) {
        echo "Partner found: " . $partner->id . " - " . $partner->name . "\n";
        echo "Status: " . $partner->status . "\n";
        echo "Is Open: " . $partner->is_open . "\n";
    } else {
        echo "No partner found for user\n";
    }
} else {
    echo "User not found\n";
}
