<?php
/**
 * Seeds / restores wallet balance for a specific user.
 * Run: php seed_user_wallet.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\WalletService;
use App\Services\WalletValidationService;

$userId  = 17;          // carl manahan
$amount  = 700.00;      // restore to ₱700
$note    = 'Wallet balance restored for testing';

$user = User::find($userId);
if (!$user) {
    echo "❌ User {$userId} not found.\n";
    exit(1);
}

$wallet = WalletValidationService::ensureWalletExists($user);
echo "📋 User     : {$user->name} (#{$user->id})\n";
echo "💰 Current balance: ₱" . number_format($wallet->balance, 2) . "\n";

if ((float) $wallet->balance >= $amount) {
    echo "✅ Balance already ₱" . number_format($wallet->balance, 2) . " — no credit needed.\n";
    exit(0);
}

$credit = $amount - (float) $wallet->balance;
WalletService::credit($user, $credit, $note);

$wallet->refresh();
echo "✅ Credited ₱" . number_format($credit, 2) . " → new balance: ₱" . number_format($wallet->balance, 2) . "\n";
