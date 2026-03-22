<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyDriverAssignedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $this->order->loadMissing(['customer', 'driverProfile.user', 'service', 'pickupAddress']);

        $driver = $this->order->driverProfile?->user;
        $customer = $this->order->customer;

        Log::channel('stack')->info('Driver assigned notification sent', [
            'order_id'    => $this->order->id,
            'driver_id'   => $this->order->driver_id,
            'driver_name' => $driver?->name,
            'customer_id' => $this->order->customer_id,
        ]);

        // TODO: notify customer that driver accepted
        // PushNotification::send($customer->fcm_token, "Your driver {$driver->name} is on the way!");

        // TODO: notify driver of pickup details
        // PushNotification::send($driver->fcm_token, "New order #{$this->order->id} — pickup at {$pickup}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyDriverAssignedJob failed', [
            'order_id' => $this->order->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
