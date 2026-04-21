# Backend Notification Integration Guide

## Quick Setup

### 1. Add FCM Token Column

```bash
php artisan make:migration add_fcm_token_to_users_table
```

**Migration:**
```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('fcm_token')->nullable()->after('remember_token');
        $table->index('fcm_token');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('fcm_token');
    });
}
```

```bash
php artisan migrate
```

### 2. Add FCM Token Endpoint

**routes/api.php:**
```php
Route::post('/v1/auth/fcm-token', [AuthController::class, 'updateFcmToken'])
    ->middleware('auth:sanctum');
```

**app/Http/Controllers/Api/AuthController.php:**
```php
public function updateFcmToken(Request $request)
{
    $request->validate([
        'fcm_token' => 'required|string|max:255',
    ]);

    $user = $request->user();
    $user->fcm_token = $request->fcm_token;
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'FCM token updated successfully',
    ]);
}
```

### 3. Install Firebase Admin SDK

```bash
composer require kreait/firebase-php
```

### 4. Download Firebase Credentials

1. Go to Firebase Console → Project Settings → Service Accounts
2. Click "Generate new private key"
3. Save as `storage/app/firebase-credentials.json`

### 5. Update .env

```env
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
FIREBASE_PROJECT_ID=your-project-id
```

### 6. Create NotificationService

**app/Services/NotificationService.php:**
```php
<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $factory = (new Factory)->withServiceAccount(
                storage_path('app/firebase-credentials.json')
            );
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Firebase initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser(int $userId, array $data)
    {
        $user = User::find($userId);
        if (!$user || !$user->fcm_token) {
            Log::warning("User {$userId} has no FCM token");
            return false;
        }

        return $this->sendToToken($user->fcm_token, $data);
    }

    /**
     * Send notification to a specific FCM token
     */
    public function sendToToken(string $token, array $data)
    {
        if (!$this->messaging) {
            Log::error('Firebase messaging not initialized');
            return false;
        }

        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create(
                    $data['title'] ?? 'Notification',
                    $data['body'] ?? ''
                ))
                ->withData($data['data'] ?? []);

            $this->messaging->send($message);
            Log::info("Notification sent to token: {$token}");
            return true;
        } catch (\Exception $e) {
            Log::error('FCM send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to a topic
     */
    public function sendToTopic(string $topic, array $data)
    {
        if (!$this->messaging) {
            return false;
        }

        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create(
                    $data['title'] ?? 'Notification',
                    $data['body'] ?? ''
                ))
                ->withData($data['data'] ?? []);

            $this->messaging->send($message);
            Log::info("Notification sent to topic: {$topic}");
            return true;
        } catch (\Exception $e) {
            Log::error('FCM send to topic failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToMultipleUsers(array $userIds, array $data)
    {
        $tokens = User::whereIn('id', $userIds)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        return $this->sendToMultipleTokens($tokens, $data);
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendToMultipleTokens(array $tokens, array $data)
    {
        if (!$this->messaging || empty($tokens)) {
            return false;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create(
                    $data['title'] ?? 'Notification',
                    $data['body'] ?? ''
                ))
                ->withData($data['data'] ?? []);

            $this->messaging->sendMulticast($message, $tokens);
            Log::info('Notification sent to ' . count($tokens) . ' tokens');
            return true;
        } catch (\Exception $e) {
            Log::error('FCM multicast send failed: ' . $e->getMessage());
            return false;
        }
    }
}
```

### 7. Register Service in AppServiceProvider

**app/Providers/AppServiceProvider.php:**
```php
use App\Services\NotificationService;

public function register()
{
    $this->app->singleton(NotificationService::class, function ($app) {
        return new NotificationService();
    });
}
```

---

## Usage Examples

### 1. Order Status Update

```php
use App\Services\NotificationService;
use App\Events\OrderStatusUpdated;

// Update order status
$order->status = 'accepted';
$order->save();

// Broadcast WebSocket event
event(new OrderStatusUpdated($order, 'pending', 'accepted'));

// Send push notification
$notificationService = app(NotificationService::class);
$notificationService->sendToUser($order->customer_id, [
    'title' => 'Order Accepted',
    'body' => 'Your driver is on the way!',
    'data' => [
        'type' => 'order_accepted',
        'order_id' => (string) $order->id,
        'route' => '/tracking/' . $order->id,
    ],
]);
```

### 2. Driver Assigned

```php
// Assign driver to order
$order->driver_id = $driver->id;
$order->status = 'accepted';
$order->save();

// Broadcast event
event(new OrderDriverAssigned($order, $driver));

// Send notification to customer
$notificationService->sendToUser($order->customer_id, [
    'title' => 'Driver Assigned',
    'body' => "{$driver->name} will deliver your order",
    'data' => [
        'type' => 'driver_assigned',
        'order_id' => (string) $order->id,
        'driver_name' => $driver->name,
        'driver_phone' => $driver->phone_number,
        'vehicle_type' => $driver->driverProfile->vehicle_type ?? '',
        'vehicle_number' => $driver->driverProfile->vehicle_number ?? '',
    ],
]);
```

### 3. Driver Arriving

```php
// Check if driver is near destination
$distanceToDestination = $this->calculateDistance(
    $driverLocation->latitude,
    $driverLocation->longitude,
    $order->dropoffAddress->latitude,
    $order->dropoffAddress->longitude
);

if ($distanceToDestination < 500) { // 500 meters
    $notificationService->sendToUser($order->customer_id, [
        'title' => 'Driver Arriving Soon',
        'body' => 'Your driver will arrive in approximately 2 minutes',
        'data' => [
            'type' => 'driver_arriving',
            'order_id' => (string) $order->id,
            'eta' => '2 minutes',
            'distance' => number_format($distanceToDestination / 1000, 1) . ' km',
        ],
    ]);
}
```

### 4. Order Picked Up

```php
$order->status = 'picked_up';
$order->picked_up_at = now();
$order->save();

event(new OrderStatusUpdated($order, 'accepted', 'picked_up'));

$notificationService->sendToUser($order->customer_id, [
    'title' => 'Order Picked Up',
    'body' => 'Your order is on the way!',
    'data' => [
        'type' => 'order_picked_up',
        'order_id' => (string) $order->id,
    ],
]);
```

### 5. Order Completed

```php
$order->status = 'completed';
$order->completed_at = now();
$order->save();

event(new OrderStatusUpdated($order, 'in_transit', 'completed'));

$notificationService->sendToUser($order->customer_id, [
    'title' => 'Order Delivered',
    'body' => 'Your order has been delivered. Thank you!',
    'data' => [
        'type' => 'order_completed',
        'order_id' => (string) $order->id,
        'route' => '/orders/' . $order->id,
    ],
]);
```

### 6. Order Cancelled

```php
$order->status = 'cancelled';
$order->cancelled_at = now();
$order->cancellation_reason = $reason;
$order->save();

event(new OrderStatusUpdated($order, $previousStatus, 'cancelled'));

$notificationService->sendToUser($order->customer_id, [
    'title' => 'Order Cancelled',
    'body' => 'Your order has been cancelled. ' . $reason,
    'data' => [
        'type' => 'order_cancelled',
        'order_id' => (string) $order->id,
        'reason' => $reason,
    ],
]);
```

### 7. Promotion Notification

```php
// Send to all users
$notificationService->sendToTopic('all_users', [
    'title' => '50% Off Today!',
    'body' => 'Get 50% off on all LesEat orders. Limited time only!',
    'data' => [
        'type' => 'promotion',
        'promo_code' => 'LESEAT50',
        'route' => '/promotions',
    ],
]);
```

---

## Notification Types Reference

| Type | Title Example | Body Example | Action |
|------|---------------|--------------|--------|
| `order_accepted` | Order Accepted | Your driver is on the way! | Open tracking |
| `order_picked_up` | Order Picked Up | Your order is on the way! | Open tracking |
| `order_completed` | Order Delivered | Your order has been delivered | Open order details |
| `order_cancelled` | Order Cancelled | Your order has been cancelled | Open order details |
| `driver_assigned` | Driver Assigned | Juan will deliver your order | Open tracking |
| `driver_arriving` | Driver Arriving Soon | Your driver will arrive in 2 minutes | Open tracking |
| `promotion` | 50% Off Today! | Get 50% off on all orders | Open promotions |
| `wallet_updated` | Wallet Updated | Your balance has been updated | Open wallet |

---

## Testing

### Test from Tinker

```bash
php artisan tinker
```

```php
// Get notification service
$service = app(\App\Services\NotificationService::class);

// Send test notification
$service->sendToUser(1, [
    'title' => 'Test Notification',
    'body' => 'This is a test notification',
    'data' => [
        'type' => 'test',
        'order_id' => '123',
    ],
]);
```

### Test Order Flow

```php
// Create test order
$order = Order::find(1);

// Test driver assigned
$service->sendToUser($order->customer_id, [
    'title' => 'Driver Assigned',
    'body' => 'Juan Dela Cruz will deliver your order',
    'data' => [
        'type' => 'driver_assigned',
        'order_id' => (string) $order->id,
    ],
]);

// Test order picked up
$service->sendToUser($order->customer_id, [
    'title' => 'Order Picked Up',
    'body' => 'Your order is on the way!',
    'data' => [
        'type' => 'order_picked_up',
        'order_id' => (string) $order->id,
    ],
]);

// Test order completed
$service->sendToUser($order->customer_id, [
    'title' => 'Order Delivered',
    'body' => 'Your order has been delivered',
    'data' => [
        'type' => 'order_completed',
        'order_id' => (string) $order->id,
    ],
]);
```

---

## Important Notes

1. **All data values must be strings** in the `data` array
2. **Always include `type` and `order_id`** in data for proper routing
3. **Test on physical device** - notifications don't work well on emulators
4. **Check Firebase Console** for delivery reports
5. **Monitor Laravel logs** for FCM errors

---

## Troubleshooting

### Notification not received
- Check if user has FCM token in database
- Check Firebase Console for delivery status
- Check Laravel logs for errors
- Verify firebase-credentials.json is valid

### Token not updating
- Check if `/v1/auth/fcm-token` endpoint is working
- Check if user is authenticated
- Check app logs for token generation

### Firebase errors
- Verify credentials file exists
- Check Firebase project ID
- Verify app is registered in Firebase Console
- Check if FCM is enabled in Firebase

---

## Production Checklist

- [ ] Firebase credentials file uploaded
- [ ] FCM token endpoint working
- [ ] Database migration run
- [ ] NotificationService registered
- [ ] Test notifications sent successfully
- [ ] Monitor Firebase Console for delivery rates
- [ ] Set up error logging
- [ ] Configure notification rate limits

---

**Ready to send notifications!** 🚀
