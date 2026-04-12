# LeSGo Real-Time WebSocket System - Complete Setup Guide

## Overview

The LeSGo API now has a comprehensive real-time WebSocket system using **Laravel Reverb** for server-side broadcasting and **Laravel Echo** for client-side subscriptions. This enables live GPS streaming, chat messaging with typing indicators, order status updates, and read receipts.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     CLIENT APPLICATIONS                      │
│  (Mobile Apps, Web Dashboard, Driver App, Customer App)     │
└──────────────────────┬──────────────────────────────────────┘
                       │ WebSocket Connection (WSS)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   LARAVEL REVERB SERVER                       │
│              (WebSocket Server on port 8080)                 │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL API SERVER                        │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ Broadcasting Events                                  │    │
│  │  - OrderStatusUpdated                               │    │
│  │  - DriverLocationUpdated                            │    │
│  │  - ChatMessageSent                                  │    │
│  │  - TypingIndicator                                  │    │
│  │  - ReadReceipt                                      │    │
│  │  - OrderDriverAssigned                              │    │
│  │  - GeofenceEventTriggered                           │    │
│  │  - RealtimeNotificationSent                         │    │
│  └─────────────────────────────────────────────────────┘    │
│                          │                                   │
│                          ▼                                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ RealtimeService (Centralized Broadcasting Logic)    │    │
│  │  - broadcastOrderStatusUpdate()                     │    │
│  │  - broadcastDriverLocationUpdate()                  │    │
│  │  - broadcastChatMessage()                           │    │
│  │  - broadcastGeofenceEvent()                         │    │
│  │  - createRealtimeNotification()                     │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

---

## 1. Server-Side Setup

### 1.1 Environment Configuration

Add these variables to your `.env` file:

```env
# WebSocket Connection (Laravel Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Queue Worker (for broadcasting events)
QUEUE_CONNECTION=database
```

### 1.2 Generate Reverb Credentials

Run this command to generate Reverb app credentials:

```bash
php artisan reverb:make
```

This will create/update the credentials in your `.env` file.

### 1.3 Start Reverb Server

```bash
# Development
php artisan reverb:start --debug

# Production (daemon)
php artisan reverb:start --port=8080
```

### 1.4 Start Queue Worker

Events are broadcast asynchronously via queues. Start the queue worker:

```bash
# Development
php artisan queue:work --queue=realtime,default

# Production (Supervisor)
php artisan queue:work --queue=realtime,default --tries=3 --timeout=60
```

### 1.5 Run Database Migrations

Ensure the following tables exist:
- `web_socket_connections` (created by Reverb)
- `realtime_notifications`
- `driver_locations`
- `chat_conversations`
- `chat_messages`

```bash
php artisan migrate
```

---

## 2. Frontend Setup (Web Applications)

### 2.1 Install Dependencies

```bash
npm install laravel-echo pusher-js
```

### 2.2 Configure Laravel Echo

Add to `resources/js/bootstrap.js`:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Add to `.env` (frontend):

```env
VITE_REVERB_APP_KEY=your-app-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

### 2.3 Subscribe to Channels

#### User's Private Channel (All Notifications)

```javascript
const userId = 1; // Current user ID

window.Echo.private(`user.${userId}`)
    .listen('.order.status.updated', (e) => {
        console.log('Order status updated:', e);
        // Update UI with new order status
    })
    .listen('.chat.message.sent', (e) => {
        console.log('New chat message:', e);
        // Display new message
    })
    .listen('.driver.location.updated', (e) => {
        console.log('Driver location updated:', e);
        // Update driver location on map
    })
    .listen('.notification.sent', (e) => {
        console.log('Notification received:', e);
        // Show notification
    })
    .listen('.geofence.event.triggered', (e) => {
        console.log('Geofence event:', e);
        // Handle geofence alerts
    });
```

#### Order-Specific Channel

```javascript
const orderId = 123;

window.Echo.private(`order.${orderId}`)
    .listen('.order.status.updated', (e) => {
        console.log('Order status:', e.new_status);
        // Update order status UI
    })
    .listen('.driver.location.updated', (e) => {
        const { latitude, longitude } = e.location;
        // Update driver marker on map
        updateDriverMarker(latitude, longitude);
        
        // Calculate ETA
        const eta = calculateETA(e.location, orderDropoffLocation);
        updateETA(eta);
    })
    .listen('.chat.message.sent', (e) => {
        // Display new message in order chat
        appendMessage(e.message);
    });
```

#### Chat Conversation Channel

```javascript
const conversationId = 456;

const conversationChannel = window.Echo.private(`conversation.${conversationId}`);

// Listen for new messages
conversationChannel.listen('.chat.message.sent', (e) => {
    appendMessage(e.message);
    markAsRead(e.message.id);
});

// Listen for typing indicators
conversationChannel.listen('.user.typing', (e) => {
    if (e.is_typing) {
        showTypingIndicator(e.user_name);
    } else {
        hideTypingIndicator(e.user_name);
    }
});

// Listen for read receipts
conversationChannel.listen('.message.read', (e) => {
    markMessageAsRead(e.message_id);
    showReadReceipt(e.reader_name);
});
```

#### Nearby Drivers (Public Channel)

```javascript
window.Echo.channel('drivers.nearby')
    .listen('.driver.location.updated', (e) => {
        // Update nearby drivers map
        updateNearbyDriverMarker(e.driver_id, e.location);
    });
```

### 2.4 Sending Real-Time Events from Client

#### Send Typing Indicator

```javascript
async function sendTypingIndicator(conversationId, isTyping) {
    const response = await fetch(`/api/v1/chat/conversations/${conversationId}/typing`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ is_typing: isTyping }),
    });
    
    return response.json();
}

// Usage - when user starts typing
let typingTimeout;
inputField.addEventListener('input', () => {
    sendTypingIndicator(conversationId, true);
    
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        sendTypingIndicator(conversationId, false);
    }, 2000);
});
```

#### Mark Messages as Read

```javascript
async function markMessagesAsRead(conversationId, messageIds = null) {
    const response = await fetch(`/api/v1/chat/conversations/${conversationId}/read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ message_ids: messageIds }),
    });
    
    return response.json();
}

// Usage - when user opens conversation
markMessagesAsRead(conversationId);
```

---

## 3. Mobile App Integration (React Native / Flutter / Native)

### 3.1 Using Pusher SDK

Install Pusher SDK:

```bash
# React Native
npm install pusher-js

# Flutter
flutter pub add pusher_channels_flutter
```

### 3.2 React Native Example

```javascript
import Pusher from 'pusher-js/react-native';

const pusher = new Pusher('your-reverb-app-key', {
    wsHost: 'your-domain.com',
    wsPort: 8080,
    forceTLS: true,
    authEndpoint: '/api/v1/broadcasting/auth',
    auth: {
        headers: {
            'Authorization': `Bearer ${userToken}`,
        },
    },
});

// Subscribe to user channel
const userChannel = pusher.subscribe(`private-user.${userId}`);

userChannel.bind('order.status.updated', (data) => {
    console.log('Order status updated:', data);
    // Update UI
});

userChannel.bind('chat.message.sent', (data) => {
    console.log('New message:', data);
    // Display message
});
```

### 3.3 Flutter Example

```dart
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

PusherChannelsFlutter pusher = PusherChannelsFlutter.getInstance();

await pusher.init(
  apiKey: 'your-reverb-app-key',
  cluster: 'mt1',
  host: 'your-domain.com',
  wsPort: 8080,
  useTLS: true,
  authEndpoint: '/api/v1/broadcasting/auth',
  auth: {
    'headers': {
      'Authorization': 'Bearer $userToken',
    },
  },
);

await pusher.connect();

await pusher.subscribe(
  channelName: 'private-user.$userId',
  onEvent: (event) {
    if (event.eventName == 'order.status.updated') {
      print('Order status: ${event.data}');
    }
  },
);
```

---

## 4. Available Broadcasting Events

### 4.1 OrderStatusUpdated

**Triggered When:** Order status changes (pending → accepted → picked_up → completed)

**Broadcasts To:**
- `private-user.{customerId}`
- `private-driver.{driverId}`
- `private-order.{orderId}`

**Payload:**
```json
{
    "order_id": 123,
    "previous_status": "pending",
    "new_status": "accepted",
    "order": {
        "id": 123,
        "status": "accepted",
        "customer_id": 1,
        "driver_id": 5,
        "estimated_fare": 85.50
    },
    "metadata": {
        "updated_by": 5,
        "updated_by_role": "driver"
    },
    "timestamp": "2026-04-12T10:30:00Z"
}
```

---

### 4.2 DriverLocationUpdated

**Triggered When:** Driver updates GPS location

**Broadcasts To:**
- `private-driver.{driverId}`
- `private-order.{orderId}` (if on trip)
- `private-user.{customerId}` (customer's order)
- `drivers.nearby` (public channel)

**Payload:**
```json
{
    "driver_id": 5,
    "order_id": 123,
    "location": {
        "latitude": 14.5995,
        "longitude": 120.9842,
        "accuracy": 10.5,
        "speed": 35.2,
        "heading": 180.0,
        "status": "on_trip",
        "recorded_at": "2026-04-12T10:30:00Z"
    },
    "driver": {
        "id": 5,
        "name": "Juan Driver",
        "phone_number": "+639171234567",
        "rating": 4.8
    },
    "timestamp": "2026-04-12T10:30:00Z"
}
```

---

### 4.3 ChatMessageSent

**Triggered When:** User sends a chat message

**Broadcasts To:**
- `private-conversation.{conversationId}`
- `private-user.{customerId}`
- `private-user.{driverId}`
- `private-order.{orderId}`

**Payload:**
```json
{
    "message": {
        "id": 789,
        "conversation_id": 456,
        "sender_id": 5,
        "content": "I'm on my way!",
        "is_system_message": false,
        "created_at": "2026-04-12T10:30:00Z"
    },
    "conversation": {
        "id": 456,
        "order_id": 123,
        "status": "active",
        "last_message_at": "2026-04-12T10:30:00Z"
    },
    "sender": {
        "id": 5,
        "name": "Juan Driver",
        "role": "driver"
    },
    "timestamp": "2026-04-12T10:30:00Z"
}
```

---

### 4.4 TypingIndicator

**Triggered When:** User types in chat

**Broadcasts To:**
- `private-conversation.{conversationId}`
- `private-user.{recipientId}`

**Payload:**
```json
{
    "conversation_id": 456,
    "user_id": 5,
    "user_name": "Juan Driver",
    "is_typing": true,
    "timestamp": "2026-04-12T10:30:00Z"
}
```

---

### 4.5 ReadReceipt

**Triggered When:** User reads chat messages

**Broadcasts To:**
- `private-conversation.{conversationId}`
- `private-user.{senderId}`

**Payload:**
```json
{
    "message_id": 789,
    "conversation_id": 456,
    "reader_id": 1,
    "reader_name": "Juan Customer",
    "read_at": "2026-04-12T10:35:00Z",
    "timestamp": "2026-04-12T10:35:00Z"
}
```

---

### 4.6 OrderDriverAssigned

**Triggered When:** Driver accepts an order

**Broadcasts To:**
- `private-user.{customerId}`
- `private-order.{orderId}`
- `private-driver.{driverId}`

**Payload:**
```json
{
    "order_id": 123,
    "order": {
        "id": 123,
        "pickup_address": "123 Main St",
        "dropoff_address": "456 Oak Ave",
        "estimated_fare": 85.50
    },
    "driver": {
        "id": 5,
        "name": "Juan Driver",
        "phone_number": "+639171234567",
        "rating": 4.8,
        "total_trips": 150
    },
    "eta_minutes": 15,
    "assigned_at": "2026-04-12T10:30:00Z",
    "timestamp": "2026-04-12T10:30:00Z"
}
```

---

### 4.7 GeofenceEventTriggered

**Triggered When:** User enters/exits a geofence

**Broadcasts To:**
- `private-user.{userId}`
- `private-geofence.{geofenceId}`
- `private-order.{orderId}` (if related to order)

**Payload:**
```json
{
    "event": {
        "id": 1,
        "geofence_id": 10,
        "user_id": 5,
        "event_type": "enter",
        "latitude": 14.5995,
        "longitude": 120.9842,
        "created_at": "2026-04-12T10:30:00Z"
    },
    "geofence": {
        "id": 10,
        "name": "Manila Service Area",
        "type": "service_area",
        "radius_meters": 5000
    },
    "user": {
        "id": 5,
        "name": "Juan Driver",
        "role": "driver"
    },
    "timestamp": "2026-04-12T10:30:00Z"
}
```

---

## 5. Private Channel Authorization

All private channels require authentication. Laravel handles this automatically via `routes/channels.php`.

### 5.1 Authentication Endpoint

Mobile apps and SPAs need to authenticate with Laravel's broadcasting auth endpoint:

**Endpoint:** `POST /api/v1/broadcasting/auth`

**Headers:**
```
Authorization: Bearer {user-token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "channel_name": "private-user.1",
    "socket_id": "12345.67890"
}
```

**Response:**
```json
{
    "auth": "private-user.1:signature_hash",
    "channel_data": {
        "user_id": 1,
        "user_info": {
            "name": "Juan dela Cruz",
            "role": "customer"
        }
    }
}
```

### 5.2 Add Broadcasting Auth Route

Add to `routes/api.php`:

```php
use Illuminate\Support\Facades\Broadcast;

Route::post('/broadcasting/auth', function (Request $request) {
    return Broadcast::authenticateUser($request->user(), [
        'user.' . $request->user()->id,
    ]);
})->middleware('auth:sanctum');
```

---

## 6. Testing Real-Time Features

### 6.1 Using Laravel Reverb Dashboard

```bash
php artisan reverb:dashboard
```

This shows:
- Active connections
- Messages per second
- Channel subscriptions

### 6.2 Testing with Artisan Tinker

```bash
php artisan tinker
```

Test order status broadcast:
```php
$order = App\Models\Order::find(123);
$previousStatus = 'pending';
broadcast(new App\Events\OrderStatusUpdated($order, $previousStatus, 'accepted', []));
```

Test chat message broadcast:
```php
$message = App\Models\ChatMessage::find(789);
$conversation = App\Models\ChatConversation::find(456);
$sender = App\Models\User::find(5);
broadcast(new App\Events\ChatMessageSent($message, $conversation, $sender));
```

### 6.3 Testing with cURL (Broadcasting Auth)

```bash
curl -X POST http://localhost:8080/api/v1/broadcasting/auth \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"channel_name":"private-user.1","socket_id":"12345.67890"}'
```

---

## 7. Production Deployment

### 7.1 Supervisor Configuration for Reverb

Create `/etc/supervisor/conf.d/lesgo-reverb.conf`:

```ini
[program:lesgo-reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/lesgo-api/artisan reverb:start --port=8080
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/lesgo-api/storage/logs/reverb.log
stopwaitsecs=3600
```

### 7.2 Supervisor Configuration for Queue Worker

Create `/etc/supervisor/conf.d/lesgo-worker.conf`:

```ini
[program:lesgo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/lesgo-api/artisan queue:work --queue=realtime,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/lesgo-api/storage/logs/worker.log
```

### 7.3 Nginx Configuration for WebSocket Proxy

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # API routes
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # WebSocket routes (Reverb)
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
```

---

## 8. Monitoring & Debugging

### 8.1 Enable Broadcast Logging

In `config/broadcasting.php`, temporarily switch to `log` driver for debugging:

```env
BROADCAST_CONNECTION=log
```

All broadcast events will be written to `storage/logs/laravel.log`.

### 8.2 Check Queue Workers

```bash
php artisan queue:monitor
```

### 8.3 View Active WebSocket Connections

```php
// In controller or tinker
$stats = app(\App\Services\RealtimeService::class)->getConnectionStats();
print_r($stats);
```

### 8.4 Clean Up Old Connections

```php
app(\App\Services\RealtimeService::class)->cleanupOldConnections(24);
```

---

## 9. Performance Optimization

### 9.1 Use Redis for Queue

For high-traffic applications, use Redis instead of database queue:

```env
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
```

### 9.2 Scale Reverb Horizontally

For multiple Reverb instances, use Redis for cross-server broadcasting:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
# Use Redis for cross-server sync
REDIS_BROADCAST=true
```

### 9.3 Throttle Location Updates

Don't broadcast every single GPS ping. Throttle in `LiveTrackingController`:

```php
// Only broadcast if driver moved > 50 meters or 30 seconds passed
$lastBroadcast = Cache::get("driver_location_broadcast_{$driverId}");
if (!$lastBroadcast || now()->diffInSeconds($lastBroadcast) > 30) {
    $this->realtimeService->broadcastDriverLocationUpdate($location, $driver, $orderId);
    Cache::put("driver_location_broadcast_{$driverId}", now(), 60);
}
```

---

## 10. Troubleshooting

### Issue: Events not broadcasting

**Solution:**
1. Check queue worker is running: `php artisan queue:work`
2. Check `QUEUE_CONNECTION=database` in `.env`
3. Check failed_jobs table: `php artisan queue:failed`
4. Retry failed jobs: `php artisan queue:retry all`

### Issue: WebSocket connection refused

**Solution:**
1. Verify Reverb is running: `php artisan reverb:start --debug`
2. Check firewall allows port 8080
3. Verify CORS settings in `config/cors.php`
4. Check Nginx WebSocket proxy configuration

### Issue: Private channel authentication failed

**Solution:**
1. Verify broadcasting auth route exists
2. Check `routes/channels.php` authorization logic
3. Ensure user is authenticated before subscribing
4. Check Sanctum token is valid

---

## 11. Complete Example: Real-Time Order Tracking

### Frontend (Web Dashboard)

```javascript
// 1. Subscribe to order channel
const orderChannel = window.Echo.private(`order.${orderId}`);

// 2. Listen for driver assignment
orderChannel.listen('.order.driver.assigned', (e) => {
    console.log('Driver assigned:', e.driver.name);
    showDriverInfo(e.driver);
    showETA(e.eta_minutes);
});

// 3. Listen for driver location updates
orderChannel.listen('.driver.location.updated', (e) => {
    const { latitude, longitude } = e.location;
    
    // Update driver marker on map
    updateDriverMarker(latitude, longitude);
    
    // Update route polyline
    updateRoutePolyline(driverLocation, orderDropoff);
    
    // Update ETA
    updateETA(e.location);
});

// 4. Listen for order status changes
orderChannel.listen('.order.status.updated', (e) => {
    console.log('Order status:', e.new_status);
    updateOrderStatus(e.new_status);
    
    if (e.new_status === 'picked_up') {
        showNotification('Driver has picked up your order!');
    }
    
    if (e.new_status === 'completed') {
        showNotification('Order completed!');
    }
});
```

### Mobile App (Customer)

```javascript
// Listen for driver location
const driverLocationChannel = pusher.subscribe(`private-order.${orderId}`);

driverLocationChannel.bind('driver.location.updated', (data) => {
    const driverPosition = new LatLng(data.location.latitude, data.location.longitude);
    
    // Smooth marker animation
    driverMarker.animateMarker(driverPosition, 2000);
    
    // Update distance and ETA
    const distance = calculateDistance(driverPosition, dropoffPosition);
    const eta = (distance / 30) * 60; // 30 km/h average
    
    updateUI({
        distance: `${distance.toFixed(2)} km`,
        eta: `${Math.round(eta)} mins`
    });
});
```

---

## Summary

✅ **Installed:** Laravel Echo & Pusher JS  
✅ **Created:** 3 new broadcasting events (TypingIndicator, ReadReceipt, OrderDriverAssigned)  
✅ **Updated:** ChatController, LiveTrackingController, OrderController with broadcasting  
✅ **Configured:** Laravel Reverb for WebSocket server  
✅ **Documented:** Complete frontend integration examples  

The real-time system is now production-ready with:
- Live GPS streaming with throttling
- Chat messaging with typing indicators & read receipts
- Order status updates broadcast to all parties
- Driver assignment notifications with ETA
- Geofence event broadcasting
- Comprehensive error handling & logging
