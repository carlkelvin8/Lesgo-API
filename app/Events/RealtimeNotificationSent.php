<?php

namespace App\Events;

use App\Models\RealtimeNotification;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeNotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RealtimeNotification $notification,
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->user->id}"),
            new PrivateChannel("notifications.{$this->user->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'message' => $this->notification->message,
                'data' => $this->notification->data,
                'priority' => $this->notification->priority,
                'channel' => $this->notification->channel,
                'is_realtime' => $this->notification->is_realtime,
                'created_at' => $this->notification->created_at->toISOString(),
                'metadata' => $this->notification->metadata,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}