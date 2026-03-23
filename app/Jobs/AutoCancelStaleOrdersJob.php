<?php

namespace App\Jobs;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoCancelStaleOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Cancel orders that have been pending/searching_driver for too long.
     * Uses chunk() to avoid loading all stale orders into memory at once.
     */
    public function handle(): void
    {
        $cutoff    = now()->subMinutes(30);
        $cancelled = 0;

        Order::whereIn('status', ['pending', 'searching_driver'])
            ->where('created_at', '<', $cutoff)
            ->select(['id', 'customer_id', 'driver_id', 'partner_id', 'service_id', 'status', 'payment_method', 'payment_status'])
            ->chunk(200, function ($orders) use (&$cancelled) {
                $ids = $orders->pluck('id')->all();

                // Bulk update — one query instead of N
                Order::whereIn('id', $ids)->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => 'Auto-cancelled: no driver found within 30 minutes.',
                    'cancelled_at'  => now(),
                    'updated_at'    => now(),
                ]);

                // Broadcast per order (lightweight — only fires if listeners exist)
                foreach ($orders as $order) {
                    $order->status      = 'cancelled';
                    $order->cancelled_at = now();
                    broadcast(new OrderStatusUpdated($order))->toOthers();
                }

                $cancelled += count($ids);
            });

        Log::info('AutoCancelStaleOrdersJob: completed', ['cancelled_count' => $cancelled]);
    }
}
