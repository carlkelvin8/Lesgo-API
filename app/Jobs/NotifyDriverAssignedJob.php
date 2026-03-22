<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
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
        $this->order->loadMissing(['customer', 'driverProfile.user', 'service']);

        $customer = $this->order->customer;
        $driver   = $this->order->driverProfile?->user;

        // Notify customer that a driver accepted
        if ($customer) {
            NotificationService::send(
                user: $customer,
                type: 'driver.assigned',
                title: 'Driver On The Way',
                body: "Your driver {$driver?->name} has accepted your order #{$this->order->id} and is heading to you.",
                data: [
                    'order_id'    => $this->order->id,
                    'driver_name' => $driver?->name,
                    'driver_id'   => $this->order->driver_id,
                ],
                channel: 'push'
            );
        }

        // Notify driver of the pickup details
        if ($driver) {
            $pickup = $this->order->meta['pickup']['address'] ?? 'See app for details';

            NotificationService::send(
                user: $driver,
                type: 'order.assigned',
                title: 'New Order Assigned',
                body: "Order #{$this->order->id} — pickup at: {$pickup}",
                data: [
                    'order_id' => $this->order->id,
                    'pickup'   => $pickup,
                ],
                channel: 'push'
            );
        }

        Log::info('NotifyDriverAssignedJob: notifications sent', [
            'order_id'  => $this->order->id,
            'driver_id' => $this->order->driver_id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyDriverAssignedJob failed', [
            'order_id' => $this->order->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
