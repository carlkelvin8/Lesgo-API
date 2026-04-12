<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDriverAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public User $driver,
        public ?float $etaMinutes = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->order->customer_id}"),
            new PrivateChannel("order.{$this->order->id}"),
            new PrivateChannel("driver.{$this->driver->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.driver.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order' => [
                'id' => $this->order->id,
                'customer_id' => $this->order->customer_id,
                'pickup_address' => $this->order->pickup_address,
                'dropoff_address' => $this->order->dropoff_address,
                'estimated_fare' => $this->order->estimated_fare,
                'status' => $this->order->status,
            ],
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'phone_number' => $this->driver->phone_number,
                'profile_photo_url' => $this->driver->profile_photo_url,
                'rating' => $this->driver->driverProfile?->rating,
                'total_trips' => $this->driver->driverProfile?->total_trips,
            ],
            'eta_minutes' => $this->etaMinutes,
            'assigned_at' => now()->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
