<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopUp;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public static function credit(
        User $user,
        float $amount,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $description, $sourceType, $sourceId) {
            $wallet = WalletValidationService::ensureWalletExists($user);
            $before = (float) $wallet->balance;

            $wallet->increment('balance', $amount);
            $wallet->refresh();

            CacheService::forgetByPattern("wallets:user:{$user->id}:*");

            return WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'type'           => 'credit',
                'source_type'    => $sourceType,
                'source_id'      => $sourceId,
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => (float) $wallet->balance,
                'description'    => $description,
                'created_by'     => $user->id,
            ]);
        });
    }

    public static function debit(
        User $user,
        float $amount,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $description, $sourceType, $sourceId) {
            $wallet = WalletValidationService::ensureWalletExists($user);
            $before = (float) $wallet->balance;

            if ($before < $amount) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $wallet->decrement('balance', $amount);
            $wallet->refresh();

            CacheService::forgetByPattern("wallets:user:{$user->id}:*");

            return WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'type'           => 'debit',
                'source_type'    => $sourceType,
                'source_id'      => $sourceId,
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => (float) $wallet->balance,
                'description'    => $description,
                'created_by'     => $user->id,
            ]);
        });
    }

    public static function completeTopUp(WalletTopUp $topUp): bool
    {
        if ($topUp->isPaid()) {
            return true;
        }

        return DB::transaction(function () use ($topUp) {
            $locked = WalletTopUp::whereKey($topUp->id)->lockForUpdate()->first();

            if ($locked->isPaid()) {
                return true;
            }

            self::credit(
                $locked->user,
                (float) $locked->amount,
                'LesPay top-up via Xendit',
                WalletTopUp::class,
                $locked->id,
            );

            $locked->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            CacheService::forgetByPattern("wallets:user:{$locked->user_id}:*");

            return true;
        });
    }
}
