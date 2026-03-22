<?php

namespace App\Events;

use App\Models\DriverProfile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DriverProfile $driverProfile,
        public ?int $activeOrderId = null
    ) {}

    /**
     * Broadcast on:
     *  - private channel for the driver themselves
     *  - private channel for the customer of the active order (if any)
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("drivers.{$this->driverProfile->id}.location"),
        ];

        // If driver has an active order, push location to that customer too
        if ($this->activeOrderId) {
            $order = $this->driverProfile->orders()
                ->where('id', $this->activeOrderId)
                ->whereIn('status', ['accepted', 'picked_up'])
                ->first();

            if ($order) {
                $channels[] = new PrivateChannel("orders.customer.{$order->customer_id}");
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id'      => $this->driverProfile->id,
            'last_latitude'  => $this->driverProfile->last_latitude,
            'last_longitude' => $this->driverProfile->last_longitude,
            'status'         => $this->driverProfile->status,
            'updated_at'     => $this->driverProfile->updated_at?->toISOString(),
        ];
    }
}
