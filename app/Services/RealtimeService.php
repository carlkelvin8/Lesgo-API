<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\DriverLocation;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\RealtimeNotification;
use App\Models\WebSocketConnection;
use App\Events\OrderStatusUpdated;
use App\Events\DriverLocationUpdated;
use App\Events\ChatMessageSent;
use App\Events\GeofenceEventTriggered;
use App\Events\RealtimeNotificationSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RealtimeService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Register a WebSocket connection for a user
     */
    public function registerConnection(User $user, string $channel, array $metadata = []): WebSocketConnection
    {
        // Disconnect any existing connections for this user and channel
        WebSocketConnection::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('status', 'connected')
            ->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
            ]);

        // Create new connection
        $connection = WebSocketConnection::create([
            'user_id' => $user->id,
            'connection_id' => Str::uuid(),
            'channel' => $channel,
            'status' => 'connected',
            'connected_at' => now(),
            'metadata' => $metadata,
        ]);

        Log::info('WebSocket connection registered', [
            'user_id' => $user->id,
            'connection_id' => $connection->connection_id,
            'channel' => $channel,
        ]);

        return $connection;
    }

    /**
     * Disconnect a WebSocket connection
     */
    public function disconnectConnection(string $connectionId): void
    {
        $connection = WebSocketConnection::where('connection_id', $connectionId)->first();
        
        if ($connection) {
            $connection->disconnect();
            
            Log::info('WebSocket connection disconnected', [
                'user_id' => $connection->user_id,
                'connection_id' => $connectionId,
                'duration' => $connection->getDurationConnected(),
            ]);
        }
    }

    /**
     * Update connection ping timestamp
     */
    public function pingConnection(string $connectionId): void
    {
        $connection = WebSocketConnection::where('connection_id', $connectionId)->first();
        
        if ($connection) {
            $connection->ping();
        }
    }

    /**
     * Broadcast order status update
     */
    public function broadcastOrderStatusUpdate(Order $order, string $previousStatus, array $metadata = []): void
    {
        try {
            // Create real-time notification for customer
            $this->createRealtimeNotification(
                $order->customer_id,
                'order_status',
                'Order Status Updated',
                "Your order status has been updated to: {$order->status}",
                [
                    'order_id' => $order->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $order->status,
                ],
                'normal'
            );

            // Create notification for driver if assigned
            if ($order->driver_id) {
                // Load driver profile to get user_id
                $driverProfile = \App\Models\DriverProfile::find($order->driver_id);
                if ($driverProfile && $driverProfile->user_id) {
                    $this->createRealtimeNotification(
                        $driverProfile->user_id, // Use user_id, not driver_profile_id
                        'order_status',
                        'Order Status Updated',
                        "Order #{$order->id} status updated to: {$order->status}",
                        [
                            'order_id' => $order->id,
                            'previous_status' => $previousStatus,
                            'new_status' => $order->status,
                        ],
                        'normal'
                    );
                }
            }

            // Broadcast the event
            broadcast(new OrderStatusUpdated($order, $previousStatus, $order->status, $metadata));

            Log::info('Order status update broadcasted', [
                'order_id' => $order->id,
                'previous_status' => $previousStatus,
                'new_status' => $order->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast order status update', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast driver location update
     */
    public function broadcastDriverLocationUpdate(DriverLocation $location, User $driver, ?int $orderId = null): void
    {
        try {
            // Create notification for customer if on an order
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $this->createRealtimeNotification(
                        $order->customer_id,
                        'driver_location',
                        'Driver Location Updated',
                        'Your driver location has been updated',
                        [
                            'order_id' => $orderId,
                            'driver_id' => $driver->id,
                            'latitude' => (float) $location->latitude,
                            'longitude' => (float) $location->longitude,
                        ],
                        'low'
                    );
                }
            }

            // Broadcast the event
            broadcast(new DriverLocationUpdated($location, $driver, $orderId));

            Log::debug('Driver location update broadcasted', [
                'driver_id' => $driver->id,
                'order_id' => $orderId,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast driver location update', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast chat message
     */
    public function broadcastChatMessage(ChatMessage $message, ChatConversation $conversation, User $sender): void
    {
        try {
            // Get the recipient
            $recipientId = $conversation->customer_id === $sender->id 
                ? $conversation->driver_id 
                : $conversation->customer_id;

            // Create notification for recipient
            $this->createRealtimeNotification(
                $recipientId,
                'chat_message',
                'New Message',
                $message->is_system_message 
                    ? $message->content 
                    : "New message from {$sender->name}",
                [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'sender_id' => $sender->id,
                    'order_id' => $conversation->order_id,
                ],
                'normal'
            );

            // Update conversation last message time
            $conversation->updateLastMessageTime();

            // Broadcast the event
            broadcast(new ChatMessageSent($message, $conversation, $sender));

            Log::info('Chat message broadcasted', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'sender_id' => $sender->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast chat message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast geofence event
     */
    public function broadcastGeofenceEvent($event, $geofence, User $user, ?int $orderId = null): void
    {
        try {
            // Create notification for user
            $this->createRealtimeNotification(
                $user->id,
                'geofence_event',
                'Location Alert',
                "You have {$event->event_type}ed {$geofence->name}",
                [
                    'geofence_id' => $geofence->id,
                    'event_type' => $event->event_type,
                    'order_id' => $orderId,
                ],
                'normal'
            );

            // Create notification for order customer if different from user
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order && $order->customer_id !== $user->id) {
                    $this->createRealtimeNotification(
                        $order->customer_id,
                        'geofence_event',
                        'Driver Location Alert',
                        "Your driver has {$event->event_type}ed {$geofence->name}",
                        [
                            'geofence_id' => $geofence->id,
                            'event_type' => $event->event_type,
                            'order_id' => $orderId,
                            'driver_id' => $user->id,
                        ],
                        'normal'
                    );
                }
            }

            // Broadcast the event
            broadcast(new GeofenceEventTriggered($event, $geofence, $user, $orderId));

            Log::info('Geofence event broadcasted', [
                'geofence_id' => $geofence->id,
                'user_id' => $user->id,
                'event_type' => $event->event_type,
                'order_id' => $orderId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast geofence event', [
                'geofence_id' => $geofence->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create and broadcast a real-time notification
     */
    public function createRealtimeNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        string $priority = 'normal'
    ): RealtimeNotification {
        $notification = RealtimeNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'channel' => 'websocket',
            'is_realtime' => true,
        ]);

        // Mark as sent immediately
        $notification->markAsSent();

        // Broadcast the notification
        $user = User::find($userId);
        if ($user) {
            broadcast(new RealtimeNotificationSent($notification, $user));
        }

        // Send push notification for high priority notifications
        if (in_array($priority, ['high', 'urgent'])) {
            $this->notificationService->sendPushNotification($user, $title, $message, $data);
        }

        return $notification;
    }

    /**
     * Get active connections for a user
     */
    public function getUserConnections(int $userId): array
    {
        return WebSocketConnection::forUser($userId)
            ->connected()
            ->active()
            ->get()
            ->toArray();
    }

    /**
     * Get connection statistics
     */
    public function getConnectionStats(): array
    {
        return [
            'total_connections' => WebSocketConnection::count(),
            'active_connections' => WebSocketConnection::connected()->active()->count(),
            'connections_by_channel' => WebSocketConnection::connected()
                ->active()
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
            'recent_disconnections' => WebSocketConnection::disconnected()
                ->where('disconnected_at', '>=', now()->subHour())
                ->count(),
        ];
    }

    /**
     * Clean up old connections
     */
    public function cleanupOldConnections(int $hoursOld = 24): int
    {
        $count = WebSocketConnection::where('created_at', '<', now()->subHours($hoursOld))
            ->delete();

        Log::info('Cleaned up old WebSocket connections', ['count' => $count]);

        return $count;
    }
}