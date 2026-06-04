<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    /**
     * Refund wallet payments and close pending gateway payments when an order is cancelled.
     */
    public static function process(Order $order): void
    {
        if ($order->status === 'cancelled') {
            return;
        }

        DB::transaction(function () use ($order) {
            $method = strtolower((string) ($order->payment_method ?? 'cash'));
            $paymentStatus = strtolower((string) ($order->payment_status ?? 'pending'));

            if ($method === 'wallet' && $paymentStatus === 'paid') {
                $customer = $order->customer ?? User::find($order->customer_id);

                if ($customer) {
                    $amount = OrderWalletPaymentService::resolvePayableAmount($order);

                    if ($amount > 0) {
                        WalletService::credit(
                            $customer,
                            $amount,
                            "Refund for cancelled order #{$order->id}",
                            Order::class,
                            $order->id,
                        );

                        Payment::where('order_id', $order->id)
                            ->where('status', 'paid')
                            ->update(['status' => 'refunded']);

                        $order->payment_status = 'refunded';
                    }
                }
            } elseif (in_array($paymentStatus, ['pending', 'failed'], true)) {
                Payment::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);

                if ($paymentStatus === 'pending') {
                    $order->payment_status = 'failed';
                }
            }

            CacheService::forgetByPattern("wallets:user:{$order->customer_id}:*");
        });
    }
}
