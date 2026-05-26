<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderWalletPaymentService
{
    public static function resolvePayableAmount(Order $order): float
    {
        $order->refresh();
        $meta = is_array($order->meta) ? $order->meta : [];

        if (!empty($meta['grand_total'])) {
            return round((float) $meta['grand_total'], 2);
        }

        if (!empty($meta['total'])) {
            $total    = (float) $meta['total'];
            $discount = (float) ($order->discount_amount ?? 0);

            return round(max(0, $total - $discount), 2);
        }

        $fare     = (float) ($order->estimated_fare ?? 0);
        $discount = (float) ($order->discount_amount ?? 0);

        return round(max(0, $fare - $discount), 2);
    }

    /**
     * Debit LesPay wallet and mark order as paid.
     *
     * @throws \RuntimeException when balance is insufficient or amount invalid
     */
    public static function payWithWallet(Order $order): Payment
    {
        $amount = self::resolvePayableAmount($order);

        if ($amount <= 0) {
            throw new \RuntimeException('Invalid order amount for LesPay payment.');
        }

        $customer = $order->customer ?? User::findOrFail($order->customer_id);

        return DB::transaction(function () use ($order, $customer, $amount) {
            WalletService::debit(
                $customer,
                $amount,
                "LesPay payment for order #{$order->id}",
                Order::class,
                $order->id,
            );

            $payment = Payment::create([
                'order_id'    => $order->id,
                'customer_id' => $customer->id,
                'partner_id'  => $order->partner_id,
                'amount'      => $amount,
                'currency'    => 'PHP',
                'method'      => 'wallet',
                'status'      => 'paid',
                'provider'    => 'lespay',
                'paid_at'     => now(),
                'meta'        => ['source' => 'order_checkout'],
            ]);

            $order->update([
                'payment_status' => 'paid',
                'payment_method' => 'wallet',
            ]);

            CacheService::forgetByPattern("wallets:user:{$customer->id}:*");

            return $payment;
        });
    }
}
