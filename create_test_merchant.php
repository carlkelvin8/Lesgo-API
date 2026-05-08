<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Partner;
use Illuminate\Support\Facades\Hash;

echo "🏪 Creating Test Merchant Account...\n\n";

// Create merchant user
$user = User::where('email', 'merchant@test.com')->first();

if (!$user) {
    $user = new User();
    $user->name = 'Test Restaurant';
    $user->email = 'merchant@test.com';
    $user->phone_number = '+639171234567';
    $user->role = 'partner';
    $user->password = Hash::make('password123');
    $user->email_verified_at = now();
    $user->save();
}

echo "✅ User created: {$user->email}\n";
echo "   Role: {$user->role}\n";
echo "   Password: password123\n\n";

// Create partner profile
$partner = Partner::firstOrCreate(
    ['user_id' => $user->id],
    [
        'name' => 'Test Restaurant',
        'legal_name' => 'Test Restaurant Inc.',
        'slug' => 'test-restaurant',
        'business_type' => 'restaurant',
        'category' => 'restaurant',
        'description' => 'A test restaurant for development',
        'logo_url' => 'https://via.placeholder.com/150',
        'cover_image_url' => 'https://via.placeholder.com/800x400',
        'rating' => 4.5,
        'total_reviews' => 10,
        'delivery_fee' => 30.00,
        'min_order_amount' => 100,
        'estimated_delivery_minutes' => 30,
        'is_open' => true,
        'is_featured' => true,
        'accepts_online_payment' => true,
        'status' => 'active',
        'support_email' => 'support@testrestaurant.com',
        'support_phone' => '+639171234567',
        'tags' => ['Filipino', 'Fast Food'],
        'cuisine_types' => ['Filipino', 'Asian'],
        'opening_hours' => [
            'monday' => ['open' => '08:00', 'close' => '22:00'],
            'tuesday' => ['open' => '08:00', 'close' => '22:00'],
            'wednesday' => ['open' => '08:00', 'close' => '22:00'],
            'thursday' => ['open' => '08:00', 'close' => '22:00'],
            'friday' => ['open' => '08:00', 'close' => '22:00'],
            'saturday' => ['open' => '08:00', 'close' => '22:00'],
            'sunday' => ['open' => '08:00', 'close' => '22:00'],
        ],
    ]
);

echo "✅ Partner profile created: {$partner->name}\n";
echo "   Partner ID: {$partner->id}\n";
echo "   Slug: {$partner->slug}\n";
echo "   Status: {$partner->status}\n\n";

echo "🎉 Test Merchant Account Ready!\n\n";
echo "📱 Login Credentials:\n";
echo "   Email: merchant@test.com\n";
echo "   Password: password123\n\n";
echo "✅ You can now login to the merchant side of the app!\n";
