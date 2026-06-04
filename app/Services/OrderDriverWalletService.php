<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;

class OrderDriverWalletService
{
    /**
     * Amount deducted from the driver's wallet when accepting a booking.
     */
    public static function resolveBookingChargeAmount(Order $order): float
    {
        $amount = OrderWalletPaymentService::resolvePayableAmount($order);

        if ($amount > 0) {
            return $amount;
        }

        $fare = (float) ($order->actual_fare ?? $order->estimated_fare ?? 0);

        return round(max(0, $fare), 2);
    }

    /**
     * Debit the driver's wallet for the booking total (idempotent per order).
     *
     * @throws \RuntimeException when balance is insufficient
     */
    public static function chargeDriverOnAccept(User $driver, Order $order): void
    {
        if (!$driver->isDriver()) {
            throw new \RuntimeException('Only drivers can accept paid bookings.');
        }

        $amount = self::resolveBookingChargeAmount($order);
        if ($amount <= 0) {
            return;
        }

        $meta = is_array($order->meta) ? $order->meta : [];
        if (!empty($meta['driver_wallet_charged_amount'])) {
            return;
        }

        WalletValidationService::ensureWalletExists($driver);

        $balance = WalletValidationService::getWalletBalance($driver);
        if ($balance < $amount) {
            throw new \RuntimeException(
                sprintf(
                    'Insufficient wallet balance. Booking total: PHP %.2f. Available: PHP %.2f.',
                    $amount,
                    $balance
                )
            );
        }

        WalletService::debit(
            $driver,
            $amount,
            "Booking charge for order #{$order->id}",
            Order::class,
            $order->id,
        );

        $meta['driver_wallet_charged_amount'] = $amount;
        $meta['driver_wallet_charged_at']     = now()->toIso8601String();
        $order->meta = $meta;
    }

    /**
     * Refund the driver's booking charge when an accepted order is cancelled.
     */
    public static function refundDriverOnCancel(Order $order): void
    {
        $order->loadMissing('driverProfile.user');

        $driver = $order->driverProfile?->user;
        if (!$driver) {
            return;
        }

        $meta = is_array($order->meta) ? $order->meta : [];
        $charged = (float) ($meta['driver_wallet_charged_amount'] ?? 0);

        if ($charged <= 0 || !empty($meta['driver_wallet_refunded'])) {
            return;
        }

        $alreadyRefunded = WalletTransaction::query()
            ->where('type', 'credit')
            ->where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('description', 'like', '%Refund for cancelled order%')
            ->exists();

        if ($alreadyRefunded) {
            return;
        }

        WalletService::credit(
            $driver,
            $charged,
            "Refund for cancelled order #{$order->id}",
            Order::class,
            $order->id,
        );

        $meta['driver_wallet_refunded']    = true;
        $meta['driver_wallet_refunded_at'] = now()->toIso8601String();
        $order->meta = $meta;
        $order->saveQuietly();
    }
}
