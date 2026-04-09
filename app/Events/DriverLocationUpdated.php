<?php

namespace App\Events;

use App\Models\DriverLocation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DriverLocation $location,
        public User $driver,
        public ?int $orderId = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("driver.{$this->driver->id}"),
        ];

        // If driver is on an order, broadcast to customer
        if ($this->orderId) {
            $channels[] = new PrivateChannel("order.{$this->orderId}");
            
            // Get customer ID from order
            $order = \App\Models\Order::find($this->orderId);
            if ($order) {
                $channels[] = new PrivateChannel("user.{$order->customer_id}");
            }
        }

        // Broadcast to nearby users (for driver discovery)
        $channels[] = new Channel("drivers.nearby");

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driver->id,
            'order_id' => $this->orderId,
            'location' => [
                'id' => $this->location->id,
                'latitude' => (float) $this->location->latitude,
                'longitude' => (float) $this->location->longitude,
                'accuracy' => (float) $this->location->accuracy,
                'speed' => (float) $this->location->speed,
                'heading' => (float) $this->location->heading,
                'altitude' => (float) $this->location->altitude,
                'status' => $this->location->status,
                'recorded_at' => $this->location->recorded_at->toISOString(),
            ],
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'phone_number' => $this->driver->phone_number,
                'profile' => $this->driver->driverProfile?->toArray(),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}