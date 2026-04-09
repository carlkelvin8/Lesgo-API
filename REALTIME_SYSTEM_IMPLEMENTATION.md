# Real-Time System Implementation

## Overview
Complete real-time communication system using Laravel Reverb (WebSockets) for live order tracking, driver location updates, instant notifications, live chat, and real-time geofence events.

## Features Implemented

### 🔄 **Real-Time Order Tracking**
- Live order status updates via WebSocket
- Automatic notifications to customers and drivers
- Real-time status broadcasting to all relevant parties
- Order progress tracking with timestamps

### 📍 **Driver Location Streaming**
- Real-time driver location updates
- Live tracking for customers during active orders
- Nearby driver discovery
- Location history and analytics
- GPS accuracy and speed tracking

### 💬 **Live Chat System**
- Real-time chat between customers and drivers
- Order-specific conversations
- System messages and notifications
- Message read receipts
- File and location sharing support

### 🔔 **Instant Notifications**
- Real-time WebSocket notifications
- Multi-priority notification system
- Push notification integration
- Notification history and management
- Read/unread status tracking

### 🗺️ **Real-Time Geofence Events**
- Live geofence enter/exit notifications
- Real-time location-based alerts
- Automatic event broadcasting
- Geofence analytics and tracking

## Database Schema

### Chat System Tables

#### chat_conversations
```sql
- id (primary key)
- order_id (foreign key to orders)
- customer_id (foreign key to users)
- driver_id (foreign key to users)
- status (enum: active, ended, archived)
- last_message_at (timestamp)
- metadata (json)
- created_at, updated_at
```

#### chat_messages
```sql
- id (primary key)
- conversation_id (foreign key to chat_conversations)
- sender_id (foreign key to users)
- sender_type (enum: customer, driver, system)
- message_type (enum: text, image, location, system, file)
- content (text)
- attachments (json)
- read_at (timestamp)
- is_system_message (boolean)
- metadata (json)
- created_at, updated_at
```

#### driver_locations
```sql
- id (primary key)
- driver_id (foreign key to users)
- order_id (foreign key to orders, nullable)
- latitude, longitude (decimal)
- accuracy, speed, heading, altitude (decimal)
- status (enum: online, offline, busy)
- recorded_at (timestamp)
- metadata (json)
- created_at, updated_at
```

#### realtime_notifications
```sql
- id (primary key)
- user_id (foreign key to users)
- type (string: order_status, driver_location, chat_message, geofence_event)
- title, message (string)
- data (json)
- priority (enum: low, normal, high, urgent)
- channel (enum: websocket, push, sms, email)
- sent_at, read_at (timestamp)
- is_realtime (boolean)
- metadata (json)
- created_at, updated_at
```

#### websocket_connections
```sql
- id (primary key)
- user_id (foreign key to users)
- connection_id (string, unique)
- channel (string)
- status (enum: connected, disconnected)
- connected_at, last_ping_at, disconnected_at (timestamp)
- metadata (json)
- created_at, updated_at
```

## API Endpoints

### WebSocket Connection Management
- `POST /api/v1/realtime/connect` - Register WebSocket connection
- `POST /api/v1/realtime/disconnect` - Disconnect WebSocket connection
- `POST /api/v1/realtime/ping` - Update connection ping
- `GET /api/v1/realtime/connections` - Get user's active connections
- `GET /api/v1/realtime/stats` - Get connection statistics (admin)

### Real-Time Notifications
- `GET /api/v1/realtime/notifications` - Get user notifications
- `POST /api/v1/realtime/notifications/{id}/read` - Mark notification as read
- `POST /api/v1/realtime/notifications/read-all` - Mark all notifications as read
- `POST /api/v1/realtime/test-notification` - Send test notification (dev)

### Live Chat System
- `GET /api/v1/chat/conversations` - Get user's conversations
- `GET /api/v1/chat/conversations/order/{order}` - Get/create order conversation
- `GET /api/v1/chat/conversations/{id}/messages` - Get conversation messages
- `POST /api/v1/chat/conversations/{id}/messages` - Send message
- `POST /api/v1/chat/conversations/{id}/system-message` - Send system message
- `POST /api/v1/chat/conversations/{id}/end` - End conversation
- `GET /api/v1/chat/unread-count` - Get unread message count

### Live Tracking System
- `POST /api/v1/tracking/driver/location` - Update driver location
- `GET /api/v1/tracking/driver/{id}/location` - Get driver current location
- `GET /api/v1/tracking/driver/{id}/history` - Get driver location history
- `GET /api/v1/tracking/order/{id}/live` - Get live order tracking
- `GET /api/v1/tracking/drivers/nearby` - Find nearby drivers
- `GET /api/v1/tracking/stats` - Get tracking statistics

## WebSocket Events

### Order Events
```javascript
// Order status updated
{
  "event": "order.status.updated",
  "data": {
    "order_id": 123,
    "previous_status": "pending",
    "new_status": "confirmed",
    "order": { /* order details */ },
    "metadata": { /* additional data */ },
    "timestamp": "2026-04-09T12:00:00Z"
  }
}
```

### Driver Location Events
```javascript
// Driver location updated
{
  "event": "driver.location.updated",
  "data": {
    "driver_id": 456,
    "order_id": 123,
    "location": {
      "latitude": 14.5995,
      "longitude": 120.9842,
      "accuracy": 10.5,
      "speed": 25.0,
      "heading": 180.0,
      "status": "online",
      "recorded_at": "2026-04-09T12:00:00Z"
    },
    "driver": { /* driver details */ },
    "timestamp": "2026-04-09T12:00:00Z"
  }
}
```

### Chat Events
```javascript
// Chat message sent
{
  "event": "chat.message.sent",
  "data": {
    "message": {
      "id": 789,
      "conversation_id": 101,
      "sender_id": 456,
      "content": "Hello, I'm on my way!",
      "message_type": "text",
      "created_at": "2026-04-09T12:00:00Z"
    },
    "conversation": { /* conversation details */ },
    "sender": { /* sender details */ },
    "timestamp": "2026-04-09T12:00:00Z"
  }
}
```

### Geofence Events
```javascript
// Geofence event triggered
{
  "event": "geofence.event.triggered",
  "data": {
    "event": {
      "id": 321,
      "geofence_id": 111,
      "user_id": 456,
      "event_type": "enter",
      "latitude": 14.5995,
      "longitude": 120.9842,
      "created_at": "2026-04-09T12:00:00Z"
    },
    "geofence": { /* geofence details */ },
    "user": { /* user details */ },
    "order_id": 123,
    "timestamp": "2026-04-09T12:00:00Z"
  }
}
```

### Notification Events
```javascript
// Real-time notification sent
{
  "event": "notification.sent",
  "data": {
    "notification": {
      "id": 654,
      "type": "order_status",
      "title": "Order Status Updated",
      "message": "Your order has been confirmed",
      "priority": "normal",
      "data": { /* notification data */ },
      "created_at": "2026-04-09T12:00:00Z"
    },
    "user": { /* user details */ },
    "timestamp": "2026-04-09T12:00:00Z"
  }
}
```

## WebSocket Channels

### Private Channels (Authentication Required)
- `user.{userId}` - User-specific notifications
- `driver.{driverId}` - Driver-specific updates
- `order.{orderId}` - Order-specific events
- `conversation.{conversationId}` - Chat conversation events
- `notifications.{userId}` - User notification events
- `geofence.{geofenceId}` - Geofence-specific events

### Public Channels
- `drivers.nearby` - Public nearby driver discovery

## Services

### RealtimeService
Core service for managing real-time operations:

```php
// Register WebSocket connection
$connection = $realtimeService->registerConnection($user, $channel, $metadata);

// Broadcast order status update
$realtimeService->broadcastOrderStatusUpdate($order, $previousStatus, $metadata);

// Broadcast driver location update
$realtimeService->broadcastDriverLocationUpdate($location, $driver, $orderId);

// Broadcast chat message
$realtimeService->broadcastChatMessage($message, $conversation, $sender);

// Broadcast geofence event
$realtimeService->broadcastGeofenceEvent($event, $geofence, $user, $orderId);

// Create real-time notification
$notification = $realtimeService->createRealtimeNotification(
    $userId, $type, $title, $message, $data, $priority
);
```

## Integration Points

### Order Status Updates
The `OrderController::updateStatus` method automatically broadcasts real-time updates:

```php
// Broadcast real-time status update with previous status
$previousStatus = $order->getOriginal('status') ?? 'pending';
$this->realtimeService->broadcastOrderStatusUpdate($order, $previousStatus, [
    'updated_by' => $user->id,
    'updated_by_role' => $user->role,
    'timestamp' => now()->toISOString(),
]);
```

### Geofence Events
The `GeofencingService::processGeofenceEvent` method broadcasts geofence events:

```php
// Broadcast real-time geofence event
$this->realtimeService->broadcastGeofenceEvent($event, $geofence, $user, $event->order_id);
```

### Driver Location Updates
The `LiveTrackingController::updateDriverLocation` method broadcasts location updates:

```php
// Broadcast location update
$this->realtimeService->broadcastDriverLocationUpdate($location, $user, $orderId);
```

## Client Integration

### JavaScript/TypeScript Example
```javascript
// Connect to WebSocket
const echo = new Echo({
    broadcaster: 'reverb',
    key: 'your-reverb-key',
    wsHost: 'your-websocket-host',
    wsPort: 8080,
    wssPort: 443,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Listen to user-specific notifications
echo.private(`user.${userId}`)
    .listen('notification.sent', (e) => {
        console.log('New notification:', e.notification);
        showNotification(e.notification);
    });

// Listen to order updates
echo.private(`order.${orderId}`)
    .listen('order.status.updated', (e) => {
        console.log('Order status updated:', e.order);
        updateOrderStatus(e.order);
    })
    .listen('driver.location.updated', (e) => {
        console.log('Driver location updated:', e.location);
        updateDriverLocation(e.location);
    });

// Listen to chat messages
echo.private(`conversation.${conversationId}`)
    .listen('chat.message.sent', (e) => {
        console.log('New message:', e.message);
        addMessageToChat(e.message);
    });
```

### Flutter/Dart Example
```dart
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

// Initialize Pusher
final pusher = PusherChannelsFlutter.getInstance();
await pusher.init(
  apiKey: 'your-reverb-key',
  cluster: 'your-cluster',
  onConnectionStateChange: onConnectionStateChange,
  onError: onError,
);

// Subscribe to user channel
final userChannel = await pusher.subscribe(
  channelName: 'private-user.$userId',
);

// Listen to notifications
await userChannel.bind(
  eventName: 'notification.sent',
  onEvent: (event) {
    final notification = jsonDecode(event.data);
    showNotification(notification['notification']);
  },
);

// Subscribe to order channel
final orderChannel = await pusher.subscribe(
  channelName: 'private-order.$orderId',
);

// Listen to order updates
await orderChannel.bind(
  eventName: 'order.status.updated',
  onEvent: (event) {
    final orderData = jsonDecode(event.data);
    updateOrderStatus(orderData['order']);
  },
);
```

## Configuration

### Environment Variables
```env
# Broadcasting
BROADCAST_CONNECTION=reverb

# Reverb WebSocket Server
REVERB_APP_ID=lesgo-app
REVERB_APP_KEY=lesgo-reverb-key
REVERB_APP_SECRET=lesgo-reverb-secret
REVERB_HOST=localhost
REVERB_PORT=8081
REVERB_SCHEME=http

# Queue for real-time events
QUEUE_CONNECTION=redis
```

### Laravel Reverb Configuration
```php
// config/broadcasting.php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST'),
        'port' => env('REVERB_PORT', 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
        'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
    ],
],
```

## Performance Considerations

### Connection Management
- Automatic connection cleanup for inactive connections
- Connection pooling and reuse
- Ping/pong heartbeat mechanism
- Graceful disconnection handling

### Message Broadcasting
- Queue-based event processing
- Priority-based message delivery
- Message deduplication
- Batch processing for bulk updates

### Database Optimization
- Indexed queries for real-time data
- Efficient location-based queries
- Connection state caching
- Message history pagination

## Security Features

### Authentication
- Sanctum token-based WebSocket authentication
- Channel-specific authorization
- User role-based access control
- Connection validation and verification

### Data Protection
- Encrypted WebSocket connections (WSS)
- Message content validation
- Rate limiting for connections
- Audit logging for all events

## Monitoring & Analytics

### Connection Metrics
- Active connection count
- Connection duration tracking
- Channel subscription analytics
- Disconnection reason tracking

### Message Analytics
- Message delivery rates
- Event processing times
- Error rate monitoring
- User engagement metrics

### Performance Monitoring
- WebSocket server performance
- Database query optimization
- Memory usage tracking
- Network bandwidth monitoring

## Deployment

### Laravel Reverb Server
```bash
# Start Reverb server
php artisan reverb:start

# Start with custom configuration
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Queue Workers
```bash
# Start queue worker for real-time events
php artisan queue:work redis --queue=realtime,default --tries=3
```

### Supervisor Configuration
```ini
[program:lesgo-reverb]
command=php artisan reverb:start
directory=/path/to/lesgo-api
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/lesgo-reverb.log

[program:lesgo-queue-realtime]
command=php artisan queue:work redis --queue=realtime,default --tries=3
directory=/path/to/lesgo-api
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/lesgo-queue-realtime.log
```

## Testing

### Unit Tests
- WebSocket connection management
- Event broadcasting functionality
- Message delivery verification
- Channel authorization testing

### Integration Tests
- End-to-end real-time communication
- Multi-user chat scenarios
- Location tracking accuracy
- Notification delivery testing

### Load Testing
- Concurrent connection handling
- Message throughput testing
- Server performance under load
- Memory usage optimization

## Troubleshooting

### Common Issues
1. **WebSocket Connection Failed**
   - Check Reverb server status
   - Verify authentication tokens
   - Confirm channel permissions

2. **Messages Not Delivered**
   - Check queue worker status
   - Verify event broadcasting
   - Confirm channel subscriptions

3. **High Memory Usage**
   - Monitor connection cleanup
   - Check message queue size
   - Optimize database queries

### Debug Commands
```bash
# Check Reverb server status
php artisan reverb:ping

# Monitor queue jobs
php artisan queue:monitor

# Clear WebSocket connections
php artisan tinker
>>> App\Models\WebSocketConnection::where('status', 'connected')->update(['status' => 'disconnected']);
```

## Future Enhancements

### Planned Features
- Voice message support in chat
- Video call integration
- Advanced location sharing
- Real-time collaborative features
- Enhanced analytics dashboard

### Scalability Improvements
- Redis Cluster support
- Multi-server WebSocket deployment
- CDN integration for static assets
- Database sharding for high volume

---

**Status**: ✅ COMPLETE - Real-time system fully implemented and ready for deployment
**Last Updated**: April 9, 2026
**Version**: 1.0.0

## Quick Start Guide

1. **Start Reverb Server**: `php artisan reverb:start`
2. **Start Queue Worker**: `php artisan queue:work redis --queue=realtime`
3. **Connect WebSocket**: `POST /api/v1/realtime/connect`
4. **Subscribe to Channels**: Use Laravel Echo or Pusher client
5. **Test Notifications**: `POST /api/v1/realtime/test-notification`

The real-time system is now fully operational and ready to provide live updates, chat, tracking, and notifications to your LeSGo application users!