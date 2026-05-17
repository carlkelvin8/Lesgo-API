<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Partner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$user = User::firstOrCreate(
    ['email' => 'merchant@test.com'],
    [
        'name' => 'Test Restaurant',
        'password' => Hash::make('Password123!'),
        'role' => 'partner_admin',
        'phone_number' => '+639171234567',
    ]
);

$partner = Partner::firstOrCreate(
    ['user_id' => $user->id],
    [
        'name' => 'Test Restaurant Business',
        'slug' => Str::slug('Test Restaurant Business'),
        'support_phone' => '09498787543',
        'support_email' => 'carlmanahan@gmail.com'
    ]
);

echo "Created Partner ID: {$partner->id}\n";
