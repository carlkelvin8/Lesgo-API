<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $previousStatus,
        public string $newStatus,
        public ?array $metadata = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("user.{$this->order->customer_id}"),
            new PrivateChannel("order.{$this->order->id}"),
        ];

        if ($this->order->driver_id) {
            $channels[] = new PrivateChannel("driver.{$this->order->driver_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'order' => [
                'id' => $this->order->id,
                'status' => $this->order->status,
                'customer_id' => $this->order->customer_id,
                'driver_id' => $this->order->driver_id,
                'service_id' => $this->order->service_id,
                'pickup_address' => $this->order->pickupAddress?->toArray(),
                'dropoff_address' => $this->order->dropoffAddress?->toArray(),
                'estimated_fare' => $this->order->estimated_fare,
                'actual_fare' => $this->order->actual_fare,
                'scheduled_at' => $this->order->scheduled_at?->toISOString(),
                'accepted_at' => $this->order->accepted_at?->toISOString(),
                'picked_up_at' => $this->order->picked_up_at?->toISOString(),
                'completed_at' => $this->order->completed_at?->toISOString(),
                'cancelled_at' => $this->order->cancelled_at?->toISOString(),
                'updated_at' => $this->order->updated_at->toISOString(),
            ],
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}