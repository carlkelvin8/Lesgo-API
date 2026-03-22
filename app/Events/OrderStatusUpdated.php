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

    public function __construct(public Order $order) {}

    /**
     * Broadcast on:
     *  - private channel for the customer
     *  - private channel for the assigned driver (if any)
     *  - private channel for the partner (if any)
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("orders.customer.{$this->order->customer_id}"),
        ];

        if ($this->order->driver_id) {
            $channels[] = new PrivateChannel("orders.driver.{$this->order->driver_id}");
        }

        if ($this->order->partner_id) {
            $channels[] = new PrivateChannel("orders.partner.{$this->order->partner_id}");
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
            'order_id'       => $this->order->id,
            'status'         => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'driver_id'      => $this->order->driver_id,
            'updated_at'     => $this->order->updated_at?->toISOString(),
        ];
    }
}
