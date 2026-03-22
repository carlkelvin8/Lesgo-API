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
     */
    public function handle(): void
    {
        $cutoff = now()->subMinutes(30);

        $stale = Order::whereIn('status', ['pending', 'searching_driver'])
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($stale as $order) {
            $order->update([
                'status'        => 'cancelled',
                'cancel_reason' => 'Auto-cancelled: no driver found within 30 minutes.',
                'cancelled_at'  => now(),
            ]);

            broadcast(new OrderStatusUpdated($order))->toOthers();

            Log::info('AutoCancelStaleOrdersJob: order cancelled', ['order_id' => $order->id]);
        }

        Log::info('AutoCancelStaleOrdersJob: completed', ['cancelled_count' => $stale->count()]);
    }
}
