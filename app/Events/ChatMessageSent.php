<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public ChatConversation $conversation,
        public User $sender
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversation->id}"),
            new PrivateChannel("user.{$this->conversation->customer_id}"),
            new PrivateChannel("user.{$this->conversation->driver_id}"),
            new PrivateChannel("order.{$this->conversation->order_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'sender_type' => $this->message->sender_type,
                'message_type' => $this->message->message_type,
                'content' => $this->message->content,
                'attachments' => $this->message->attachments,
                'is_system_message' => $this->message->is_system_message,
                'created_at' => $this->message->created_at->toISOString(),
                'metadata' => $this->message->metadata,
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'order_id' => $this->conversation->order_id,
                'customer_id' => $this->conversation->customer_id,
                'driver_id' => $this->conversation->driver_id,
                'status' => $this->conversation->status,
                'last_message_at' => $this->conversation->last_message_at?->toISOString(),
            ],
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'role' => $this->sender->role,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}