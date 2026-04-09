<?php

namespace App\Events;

use App\Models\GeofenceEvent;
use App\Models\Geofence;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GeofenceEventTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GeofenceEvent $event,
        public Geofence $geofence,
        public User $user,
        public ?int $orderId = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("user.{$this->user->id}"),
            new PrivateChannel("geofence.{$this->geofence->id}"),
        ];

        // If related to an order, broadcast to order channel
        if ($this->orderId) {
            $channels[] = new PrivateChannel("order.{$this->orderId}");
            
            // Get order and broadcast to customer if user is driver
            $order = \App\Models\Order::find($this->orderId);
            if ($order && $this->user->id !== $order->customer_id) {
                $channels[] = new PrivateChannel("user.{$order->customer_id}");
            }
        }

        // Broadcast to geofence administrators
        if ($this->geofence->created_by) {
            $channels[] = new PrivateChannel("user.{$this->geofence->created_by}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'geofence.event.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => [
                'id' => $this->event->id,
                'geofence_id' => $this->event->geofence_id,
                'user_id' => $this->event->user_id,
                'order_id' => $this->event->order_id,
                'event_type' => $this->event->event_type,
                'latitude' => (float) $this->event->latitude,
                'longitude' => (float) $this->event->longitude,
                'accuracy' => (float) $this->event->accuracy,
                'created_at' => $this->event->created_at->toISOString(),
                'metadata' => $this->event->metadata,
            ],
            'geofence' => [
                'id' => $this->geofence->id,
                'name' => $this->geofence->name,
                'type' => $this->geofence->type,
                'shape' => $this->geofence->shape,
                'center_latitude' => (float) $this->geofence->center_latitude,
                'center_longitude' => (float) $this->geofence->center_longitude,
                'radius_meters' => $this->geofence->radius_meters,
                'polygon_coordinates' => $this->geofence->polygon_coordinates,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role,
            ],
            'order_id' => $this->orderId,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}