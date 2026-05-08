<?php

/**
 * Test Email Sending
 * 
 * This script tests if Gmail is properly configured and can send emails.
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetOtpMail;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              EMAIL CONFIGURATION TEST                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Get email configuration
$mailer = config('mail.default');
$host = config('mail.mailers.smtp.host');
$port = config('mail.mailers.smtp.port');
$username = config('mail.mailers.smtp.username');
$fromAddress = config('mail.from.address');
$fromName = config('mail.from.name');

echo "📧 Email Configuration:\n";
echo "   - Mailer: {$mailer}\n";
echo "   - Host: {$host}\n";
echo "   - Port: {$port}\n";
echo "   - Username: {$username}\n";
echo "   - From Address: {$fromAddress}\n";
echo "   - From Name: {$fromName}\n";
echo "\n";

// Test email address
$testEmail = 'testcustomer@lesgo.com';
$testOtp = '123456';
$testName = 'Carl Kelvin';
$expiryMinutes = 10;

echo "📨 Sending test email...\n";
echo "   - To: {$testEmail}\n";
echo "   - OTP: {$testOtp}\n";
echo "\n";

try {
    Mail::to($testEmail)->send(
        new PasswordResetOtpMail($testOtp, $testName, $expiryMinutes)
    );
    
    echo "✅ Email sent successfully!\n";
    echo "\n";
    echo "📬 Check your inbox:\n";
    echo "   - Email: {$testEmail}\n";
    echo "   - Subject: Reset Your Password - LeSGo\n";
    echo "   - OTP Code: {$testOtp}\n";
    echo "\n";
    echo "💡 If you don't see it:\n";
    echo "   1. Check your spam/junk folder\n";
    echo "   2. Wait a few seconds (Gmail can be slow)\n";
    echo "   3. Check the Laravel logs for errors\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ Failed to send email!\n";
    echo "\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\n";
    echo "💡 Common issues:\n";
    echo "   1. Wrong app password (check .env file)\n";
    echo "   2. 2-Step Verification not enabled on Gmail\n";
    echo "   3. App password not generated correctly\n";
    echo "   4. Firewall blocking port 587\n";
    echo "\n";
    echo "🔧 To fix:\n";
    echo "   1. Go to: https://myaccount.google.com/security\n";
    echo "   2. Enable 2-Step Verification\n";
    echo "   3. Go to App passwords\n";
    echo "   4. Generate new password for 'Mail'\n";
    echo "   5. Update MAIL_PASSWORD in .env\n";
    echo "   6. Run: php artisan config:clear\n";
    echo "\n";
    
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    TEST COMPLETE                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "🎉 Email configuration is working!\n";
echo "\n";
echo "📝 Next steps:\n";
echo "   1. Test the forgot password flow in the app\n";
echo "   2. Click 'Forgot Password?' on login screen\n";
echo "   3. Enter your email\n";
echo "   4. Check your inbox for the OTP\n";
echo "   5. Complete the password reset\n";
echo "\n";
echo "✅ Ready to use!\n";
echo "\n";
