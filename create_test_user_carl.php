<?php

/**
 * Create Test User - Carl Kelvin Manahan
 * 
 * Email: carlkelvinmanahan014@gmail.com
 * Password: password123
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Hash;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         CREATE TEST USER - CARL KELVIN MANAHAN                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$email = 'carlkelvinmanahan014@gmail.com';
$password = 'password123';
$name = 'Carl Kelvin Manahan';
$phoneNumber = '+639123456789';

echo "📧 Email: {$email}\n";
echo "🔑 Password: {$password}\n";
echo "👤 Name: {$name}\n";
echo "📱 Phone: {$phoneNumber}\n";
echo "\n";

// Check if user already exists
$existingUser = User::where('email', $email)->first();

if ($existingUser) {
    echo "⚠️  User already exists!\n";
    echo "\n";
    echo "User Details:\n";
    echo "   - ID: {$existingUser->id}\n";
    echo "   - Name: {$existingUser->name}\n";
    echo "   - Email: {$existingUser->email}\n";
    echo "   - Phone: {$existingUser->phone_number}\n";
    echo "   - Role: {$existingUser->role}\n";
    echo "   - Created: {$existingUser->created_at}\n";
    echo "\n";
    
    // Update password
    echo "🔄 Updating password to: {$password}\n";
    $existingUser->password = Hash::make($password);
    $existingUser->save();
    
    echo "✅ Password updated successfully!\n";
    echo "\n";
    echo "You can now login with:\n";
    echo "   - Email: {$email}\n";
    echo "   - Password: {$password}\n";
    echo "\n";
    
    exit(0);
}

// Create new user
echo "Creating new user...\n";

try {
    $user = User::create([
        'name' => $name,
        'email' => $email,
        'phone_number' => $phoneNumber,
        'password' => Hash::make($password),
        'role' => 'customer',
        'phone_verified_at' => now(),
    ]);
    
    // Set email_verified_at separately since it's not fillable
    $user->email_verified_at = now();
    $user->save();
    
    echo "✅ User created successfully!\n";
    echo "\n";
    echo "User Details:\n";
    echo "   - ID: {$user->id}\n";
    echo "   - Name: {$user->name}\n";
    echo "   - Email: {$user->email}\n";
    echo "   - Phone: {$user->phone_number}\n";
    echo "   - Role: {$user->role}\n";
    echo "   - Created: {$user->created_at}\n";
    echo "\n";
    echo "Login Credentials:\n";
    echo "   - Email: {$email}\n";
    echo "   - Password: {$password}\n";
    echo "\n";
    echo "🎉 Ready to use!\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ Failed to create user!\n";
    echo "\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\n";
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    ACCOUNT CREATED                             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "📝 Next Steps:\n";
echo "   1. Go to the login screen\n";
echo "   2. Enter email: {$email}\n";
echo "   3. Enter password: {$password}\n";
echo "   4. Click 'Log In'\n";
echo "\n";
echo "🔐 To test Forgot Password:\n";
echo "   1. Click 'Forgot Password?'\n";
echo "   2. Enter email: {$email}\n";
echo "   3. Check your Gmail inbox for OTP\n";
echo "   4. Complete the password reset\n";
echo "\n";
echo "✅ All set!\n";
echo "\n";
