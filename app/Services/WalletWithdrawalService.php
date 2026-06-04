<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletLinkedAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletWithdrawalService
{
    public static function channelCodeForProvider(string $provider): string
    {
        return match (strtolower($provider)) {
            'gcash'              => 'PH_GCASH',
            'maya', 'paymaya'    => 'PH_MAYA',
            default              => throw new \InvalidArgumentException(
                'Unsupported withdrawal method. Use gcash or maya.'
            ),
        };
    }

    public static function normalizeAccountNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0' . substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0' . $digits;
        }

        return $digits;
    }

    /**
     * Debit wallet and send payout to linked GCash/Maya account.
     *
     * @return array{payout: array, wallet: \App\Models\Wallet}
     */
    public static function process(User $user, float $amount, string $provider): array
    {
        $provider = strtolower($provider);
        if ($provider === 'paymaya') {
            $provider = 'maya';
        }

        $linked = WalletLinkedAccount::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (!$linked) {
            throw new \RuntimeException(
                'Link your ' . ($provider === 'gcash' ? 'GCash' : 'Maya') . ' account before withdrawing.'
            );
        }

        $meta = is_array($linked->meta) ? $linked->meta : [];
        $accountNumber = self::normalizeAccountNumber(
            (string) ($meta['account_number'] ?? '')
        );
        $holderName = trim((string) ($meta['account_holder_name'] ?? $user->name));

        if ($accountNumber === '' || strlen($accountNumber) < 10) {
            throw new \RuntimeException(
                'Linked account is missing a valid mobile number. Please re-link your account.'
            );
        }

        if ($holderName === '') {
            throw new \RuntimeException('Account holder name is required for withdrawal.');
        }

        WalletValidationService::ensureWalletExists($user);

        if (WalletValidationService::getWalletBalance($user) < $amount) {
            throw new \RuntimeException('Insufficient wallet balance.');
        }

        $referenceId = 'lespay-withdraw-' . $user->id . '-' . Str::uuid();
        $channelCode = self::channelCodeForProvider($provider);

        return DB::transaction(function () use (
            $user,
            $amount,
            $provider,
            $referenceId,
            $channelCode,
            $accountNumber,
            $holderName,
            $linked,
            $meta
        ) {
            WalletService::debit(
                $user,
                $amount,
                'Withdrawal to ' . strtoupper($provider) . ' via Xendit',
                'withdrawal',
                null,
            );

            try {
                $payout = app(PaymentGatewayService::class)->createPayout(
                    $amount,
                    $referenceId,
                    $channelCode,
                    $holderName,
                    $accountNumber,
                    'LesGo wallet withdrawal',
                );
            } catch (\Throwable $e) {
                WalletService::credit(
                    $user,
                    $amount,
                    'Withdrawal reversal — Xendit payout failed',
                    'withdrawal',
                    null,
                );
                throw new \RuntimeException($e->getMessage());
            }

            $linked->update([
                'is_verified' => true,
                'meta'        => array_merge($meta, [
                    'account_number'      => $accountNumber,
                    'account_holder_name' => $holderName,
                    'last_withdrawal_at'  => now()->toIso8601String(),
                    'last_payout_id'      => $payout['id'] ?? null,
                    'last_payout_status'  => $payout['status'] ?? null,
                ]),
            ]);

            return [
                'payout' => $payout,
                'wallet' => WalletValidationService::ensureWalletExists($user)->fresh(),
            ];
        });
    }
}
