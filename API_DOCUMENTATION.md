# LeSGo API - Complete Frontend Developer Documentation

> **Base URL:** `https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1`  
> **Auth:** Laravel Sanctum (Bearer Token)  
> **Format:** All requests/responses are JSON  
> **Version:** v1

---

## Table of Contents

1. [How Authentication Works](#1-how-authentication-works)
2. [Standard Response Format](#2-standard-response-format)
3. [Rate Limiting](#3-rate-limiting)
4. [User Roles & Permissions](#4-user-roles--permissions)
5. [Auth Endpoints](#5-auth-endpoints)
6. [User Management](#6-user-management)
7. [Services](#7-services)
8. [Orders](#8-orders)
9. [Drivers](#9-drivers)
10. [Partners & Branches](#10-partners--branches)
11. [Addresses](#11-addresses)
12. [Payments](#12-payments)
13. [Wallets](#13-wallets)
14. [Notifications](#14-notifications)
15. [Order Tracking](#15-order-tracking)
16. [Live Tracking (GPS)](#16-live-tracking-gps)
17. [Real-Time & WebSockets](#17-real-time--websockets)
18. [Live Chat](#18-live-chat)
19. [Ratings & Reviews](#19-ratings--reviews)
20. [Support Tickets](#20-support-tickets)
21. [FAQ & Help Center](#21-faq--help-center)
22. [Document Verification](#22-document-verification)
23. [Admin Document Review](#23-admin-document-review)
24. [Social Media Sharing](#24-social-media-sharing)
25. [Geofencing](#25-geofencing)
26. [Analytics](#26-analytics)
27. [Security & 2FA](#27-security--2fa)
28. [Checklist Templates](#28-checklist-templates)
29. [Distance Calculator](#29-distance-calculator)
30. [Error Reference](#30-error-reference)

---

## 1. How Authentication Works

LeSGo uses **Laravel Sanctum** token-based authentication.

### Flow
`
Register / Login  ?  Receive token  ?  Send token in every protected request header
`

### Setting the Auth Header
`
Authorization: Bearer <your_token_here>
`

### Token Lifecycle
- Token is returned on **register** and **login**
- Token is valid until the user calls **logout** or **logout-all**
- Changing password revokes all tokens and returns a new one
- Store the token in **localStorage** or **SecureStorage** (mobile)

---

## 2. Standard Response Format

Every response follows this shape:

### Success
```json
{
  "success": true,
  "message": "Human readable message",
  "data": { ... },
  "request_id": "uuid"
}
```

### Paginated List
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 98
  },
  "links": {
    "first": "...?page=1",
    "last": "...?page=5",
    "prev": null,
    "next": "...?page=2"
  }
}
```

### Error
```json
{
  "success": false,
  "message": "What went wrong",
  "errors": { "field": ["Validation message"] }
}
```

### HTTP Status Codes
| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthenticated (missing/invalid token) |
| 403 | Forbidden (wrong role or not your resource) |
| 404 | Not Found |
| 409 | Conflict (e.g. duplicate payment) |
| 422 | Validation Error |
| 429 | Too Many Requests (rate limited) |
| 500 | Server Error |

---

## 3. Rate Limiting

| Throttle Group | Limit |
|----------------|-------|
| `auth` (login/register) | 10 req / min |
| `driver-registration` | 5 req / min |
| `authenticated` (all protected routes) | 100 req / min |
| `api` (public routes) | 60 req / min |
| Payment webhooks | 60 req / min |

When rate limited you get **429** with a `Retry-After` header.

---

## 4. User Roles & Permissions

| Role | Description |
|------|-------------|
| `customer` | Books orders, tracks deliveries, chats with driver |
| `driver` | Accepts orders, updates location, chats with customer |
| `partner_admin` | Manages their partner's orders and drivers |
| `admin` | Full access to everything |

> Most endpoints are **scoped by role** — the API automatically filters data based on who is logged in. A customer only sees their own orders; a driver only sees orders assigned to them.

---

## 5. Auth Endpoints

### POST /auth/register
Create a new user account.

**Public — no token needed**

**Request Body**
```json
{
  "name": "Juan dela Cruz",
  "email": "juan@example.com",
  "phone_number": "+639171234567",
  "password": "secret123",
  "password_confirmation": "secret123",
  "role": "customer",
  "device_name": "my-phone"
}
```

**Roles:** `customer` | `driver` | `partner_admin`

**Response 201**
```json
{
  "success": true,
  "message": "Registration successful",
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Juan dela Cruz",
    "email": "juan@example.com",
    "phone_number": "+639171234567",
    "role": "customer",
    "created_at": "2026-04-09T10:00:00Z"
  }
}
```

> **Frontend tip:** Save the `token` immediately after registration. No need to call login separately.

---

### POST /auth/login
Login and get a token.

**Public — no token needed**

**Request Body**
```json
{
  "email": "juan@example.com",
  "password": "secret123",
  "device_name": "my-phone",
  "revoke_others": false
}
```

Set `revoke_others: true` to log out all other devices (useful for "sign in on this device only").

**Response 200**
```json
{
  "success": true,
  "message": "Login successful",
  "token": "2|xyz789...",
  "user": { ... }
}
```

**Errors**
- `401` — Wrong credentials
- `429` — Too many attempts (locked for 5 minutes after 5 fails)

---

### GET /auth/me
Get the currently logged-in user.

**Requires token**

**Response 200**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Juan dela Cruz",
    "email": "juan@example.com",
    "role": "customer"
  }
}
```

---

### PUT /auth/me
Update profile. To change password, include `current_password` + `password` + `password_confirmation`.

**Requires token**

**Request Body** (all fields optional)
```json
{
  "name": "Juan Santos",
  "phone_number": "+639181234567",
  "current_password": "secret123",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

> **Important:** Changing password revokes all existing tokens. The response includes a new `token` — update your stored token immediately.

---

### POST /auth/logout
Revoke the current token (this device only).

**Requires token**

---

### POST /auth/logout-all
Revoke all tokens across all devices.

**Requires token**

---

### POST /auth/fcm-token
Register a Firebase Cloud Messaging token for push notifications.

**Requires token**

**Request Body**
```json
{
  "fcm_token": "dGhpcyBpcyBhIHRlc3Q..."
}
```

> Call this every time the app starts or the FCM token refreshes.

---

## 6. User Management

All require token. Scoped by role — admins see all, others see only themselves.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/users` | List users (admin only) |
| POST | `/users` | Create user (admin only) |
| GET | `/users/{id}` | Get user by ID |
| PATCH | `/users/{id}` | Update user |
| DELETE | `/users/{id}` | Delete user (admin only) |

---

## 7. Services

**Public — no token needed**

Services are the types of delivery/errand available (e.g. LesGo, LesBuy, LesEat).

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/services` | List all active services |
| GET | `/services/{id}` | Get service details |

**Response example**
```json
{
  "id": 1,
  "name": "LesGo",
  "code": "LESGO",
  "description": "Motorcycle delivery",
  "base_fare": 40,
  "per_km_rate": 9.5,
  "is_active": true
}
```

---

## 8. Orders

The core of the app. **Requires token.**

### Order Status Flow
```
pending ? searching_driver ? accepted ? picked_up ? completed
                                    ? cancelled (any stage before completed)
```

### Who can change what status?
| Role | Allowed transitions |
|------|---------------------|
| `customer` | ? `cancelled` |
| `partner_admin` | `pending` ? `searching_driver`, ? `cancelled` |
| `driver` | ? `accepted`, `accepted` ? `picked_up`, `picked_up` ? `completed` |
| `admin` | Any transition |

---

### GET /orders
List orders. Automatically scoped by role.

**Query params**
| Param | Type | Description |
|-------|------|-------------|
| `status` | string | Filter by status |
| `payment_status` | string | `pending` \| `paid` \| `failed` |
| `service_id` | integer | Filter by service |
| `per_page` | integer | Default 20 |

---

### POST /orders
Create a new order.

**Request Body**
```json
{
  "service_id": 1,
  "pickup": {
    "address": "123 Rizal St, Manila",
    "lat": 14.5995,
    "lng": 120.9842
  },
  "dropoff": {
    "address": "456 Mabini Ave, Makati",
    "lat": 14.5547,
    "lng": 121.0244
  },
  "estimated_distance_m": 5200,
  "payment_method": "gcash",
  "save_addresses": false,
  "scheduled_at": null,
  "items": [
    {
      "name": "Jollibee Chickenjoy",
      "quantity": 2,
      "estimated_price": 150.00,
      "is_checklist_item": false
    }
  ],
  "meta": {
    "order_value": 300,
    "special_instructions": "Please ring the doorbell"
  }
}
```

**Payment methods:** `cash` | `gcash` | `maya` | `card` | `wallet`

**Fare Calculation Logic (for display purposes)**
- Base fare: ?40 for first 3km
- Additional: ?9.50/km (LesGo) or ?10/km (others)
- LesBuy/LesEat add a service fee based on order value:
  - =?500 ? +?15
  - =?1000 ? +?30
  - >?1000 ? +?45

---

### GET /orders/{id}
Get full order details including customer, driver, addresses, payments, and items.

---

### PATCH /orders/{id}/status
Update order status.

**Request Body**
```json
{
  "status": "accepted",
  "cancel_reason": null,
  "actual_distance_m": 5400
}
```

> When a driver sets status to `accepted`, they are automatically assigned to the order.

---

### GET /orders/{id}/receipt
Get a formatted receipt for a completed order.

---

## 9. Drivers

### POST /drivers/register
**Public — no token needed.** Register a new driver account.

**Request Body**
```json
{
  "name": "Pedro Santos",
  "email": "pedro@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "phone_number": "+639181234567",
  "license_number": "N01-23-456789",
  "license_expiry_date": "2028-12-31",
  "partner_id": null
}
```

> New drivers start with status `pending` — admin must approve before they can accept orders.

---

### GET /drivers
List drivers. Scoped: admins see all, partner_admins see their drivers, drivers see only themselves.

**Query params:** `status` (pending | active | offline | suspended)

---

### GET /drivers/{id}
Get driver profile with vehicles.

---

### PATCH /drivers/{id}/status
Update driver status. **Admin / partner_admin only.**

```json
{ "status": "active" }
```

---

### PATCH /drivers/{id}/location
Driver updates their own GPS location. Broadcasts real-time to the customer tracking the order.

```json
{
  "last_latitude": 14.5995,
  "last_longitude": 120.9842
}
```

---

## 10. Partners & Branches

**Requires token.**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/partners` | List partners |
| POST | `/partners` | Create partner (admin) |
| GET | `/partners/{id}` | Get partner details |
| PATCH | `/partners/{id}` | Update partner |
| GET | `/partners/{id}/branches` | List branches of a partner |
| POST | `/partners/{id}/branches` | Add branch to partner |
| PATCH | `/branches/{id}` | Update branch |
| DELETE | `/branches/{id}` | Delete branch |

---

## 11. Addresses

**Requires token.**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/users/{user_id}/addresses` | List saved addresses for a user |
| POST | `/users/{user_id}/addresses` | Save a new address |
| PATCH | `/addresses/{id}` | Update address |
| DELETE | `/addresses/{id}` | Delete address |

**Create address body**
```json
{
  "label": "Home",
  "contact_name": "Juan dela Cruz",
  "contact_phone": "+639171234567",
  "address_line1": "123 Rizal St",
  "city": "Manila",
  "country": "PH",
  "latitude": 14.5995,
  "longitude": 120.9842,
  "is_default": true
}
```

---

## 12. Payments

**Requires token.**

### GET /payments
List payments. Customers see only their own. Admins see all.

**Query params:** `order_id`, `status` (pending | paid | failed | refunded)

---

### POST /payments
Record a payment for an order.

```json
{
  "order_id": 10,
  "customer_id": 5,
  "amount": 85.50,
  "currency": "PHP",
  "method": "gcash",
  "status": "pending",
  "provider": "xendit",
  "provider_reference": "inv_abc123"
}
```

> Returns `409` if a payment already exists for the order.

---

### GET /payments/{id}
Get payment details with order and customer info.

---

### POST /webhooks/payments/{provider}
**Public — called by payment providers, not your frontend.**

Providers: `xendit` | `gcash` | `maya`

Xendit uses `X-CALLBACK-TOKEN` header for verification. GCash/Maya use HMAC signature.

---

### POST /gateway/invoice
Create a Xendit invoice for online payment.

```json
{
  "order_id": 10,
  "amount": 85.50,
  "description": "LesGo delivery payment",
  "customer_email": "juan@example.com",
  "success_redirect_url": "https://yourapp.com/payment/success",
  "failure_redirect_url": "https://yourapp.com/payment/failed"
}
```

**Response includes** `invoice_url` — redirect the user to this URL to complete payment.

---

### GET /gateway/invoice/{invoiceId}
Check invoice status.

---

### POST /gateway/invoice/{invoiceId}/expire
Manually expire an invoice.

---

### POST /gateway/refund
Issue a refund.

```json
{
  "payment_id": 5,
  "amount": 85.50,
  "reason": "Customer cancelled"
}
```

---

## 13. Wallets

**Requires token.**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wallets/{user_id}` | Get wallet balance and info |
| GET | `/wallets/{user_id}/transactions` | List wallet transactions |

**Wallet response**
```json
{
  "id": 1,
  "user_id": 5,
  "balance": 250.00,
  "currency": "PHP",
  "is_active": true
}
```

---

## 14. Notifications

**Requires token.**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notifications` | List all notifications |
| GET | `/notifications/unread-count` | Get unread count (for badge) |
| PATCH | `/notifications/{id}/read` | Mark one as read |
| POST | `/notifications/read-all` | Mark all as read |

> Poll `/notifications/unread-count` every 30 seconds or use WebSockets for real-time badge updates.

---

## 15. Order Tracking

**Requires token.**

### GET /tracking/orders/{order}
Get full tracking timeline for an order (all status change events).

**Response**
```json
{
  "order_id": 10,
  "current_status": "picked_up",
  "events": [
    { "status": "pending", "timestamp": "2026-04-09T10:00:00Z", "note": "Order placed" },
    { "status": "accepted", "timestamp": "2026-04-09T10:05:00Z", "note": "Driver accepted" },
    { "status": "picked_up", "timestamp": "2026-04-09T10:20:00Z", "note": "Package picked up" }
  ],
  "driver": { "name": "Pedro", "lat": 14.5995, "lng": 120.9842 }
}
```

---

### GET /tracking/orders/{order}/location
Get the driver's current live location for an order.

---

### POST /tracking/orders/{order}/events
Add a tracking event (driver/admin only).

```json
{
  "status": "picked_up",
  "note": "Package collected from sender",
  "latitude": 14.5995,
  "longitude": 120.9842
}
```

---

### POST /tracking/orders/multiple
Track multiple orders at once.

```json
{ "order_ids": [10, 11, 12] }
```

---

## 16. Live Tracking (GPS)

Real-time GPS tracking system. **Requires token.**

### POST /tracking/driver/location
Driver pushes their current GPS position. This also broadcasts via WebSocket to the customer.

```json
{
  "latitude": 14.5995,
  "longitude": 120.9842,
  "heading": 180,
  "speed": 30,
  "accuracy": 5
}
```

---

### GET /tracking/driver/{driver}/location
Get a driver's current location.

---

### GET /tracking/driver/{driver}/history
Get location history for a driver (admin use).

**Query params:** `from`, `to` (ISO date strings), `per_page`

---

### GET /tracking/order/{order}/live
Get live tracking data for an order — driver position + ETA.

---

### GET /tracking/drivers/nearby
Find available drivers near a location.

**Query params:** `latitude`, `longitude`, `radius_km` (default 5)

---

### GET /tracking/stats
Get tracking system statistics (admin).

---

## 17. Real-Time & WebSockets

LeSGo uses **Laravel Reverb** for WebSocket connections.

### Connection Flow
```
1. Call POST /realtime/connect  ?  get channel names
2. Connect to WebSocket server using Laravel Echo
3. Subscribe to your private channels
4. Listen for events
```

### POST /realtime/connect
Register your WebSocket connection.

```json
{ "socket_id": "abc.123", "platform": "web" }
```

**Response includes channel names to subscribe to:**
```json
{
  "channels": {
    "user": "private-user.5",
    "order": "private-order.10",
    "driver_location": "private-driver-location.3"
  }
}
```

---

### POST /realtime/disconnect
Unregister WebSocket connection on app close.

---

### POST /realtime/ping
Keep-alive ping. Call every 30 seconds to maintain connection.

---

### GET /realtime/notifications
Get real-time notifications (same as /notifications but from the realtime system).

---

### POST /realtime/notifications/{id}/read
Mark a real-time notification as read.

---

### POST /realtime/notifications/read-all
Mark all real-time notifications as read.

---

### GET /realtime/stats
WebSocket connection statistics (admin).

---

### POST /realtime/test-notification
Send a test notification to yourself (development use).

---

### WebSocket Events to Listen For

**Laravel Echo setup:**
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: process.env.VITE_REVERB_APP_KEY,
  wsHost: process.env.VITE_REVERB_HOST,
  wsPort: process.env.VITE_REVERB_PORT,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
});
```

**Subscribe to order updates:**
```javascript
Echo.private('order.' + orderId)
  .listen('OrderStatusUpdated', (e) => {
    console.log('Order status:', e.status);
  });
```

**Subscribe to driver location:**
```javascript
Echo.private('driver-location.' + driverProfileId)
  .listen('DriverLocationUpdated', (e) => {
    updateMapMarker(e.latitude, e.longitude);
  });
```

**Subscribe to chat messages:**
```javascript
Echo.private('chat.' + conversationId)
  .listen('ChatMessageSent', (e) => {
    appendMessage(e.message);
  });
```

**Subscribe to notifications:**
```javascript
Echo.private('user.' + userId)
  .listen('RealtimeNotificationSent', (e) => {
    showNotification(e.title, e.body);
  });
```

---

## 18. Live Chat

Customer and driver can chat during an active order. **Requires token.**

### GET /chat/conversations
List all conversations for the logged-in user.

---

### GET /chat/conversations/order/{order}
Get or create a conversation for a specific order. Use this to open the chat screen.

**Response**
```json
{
  "id": 1,
  "order_id": 10,
  "customer_id": 5,
  "driver_id": 3,
  "status": "active",
  "unread_count": 2
}
```

---

### GET /chat/conversations/{conversation}/messages
Get all messages in a conversation (paginated, newest last).

---

### POST /chat/conversations/{conversation}/messages
Send a message.

```json
{
  "message": "I am 5 minutes away",
  "type": "text"
}
```

Message types: `text` | `image` | `location`

> This broadcasts a `ChatMessageSent` WebSocket event to the other party.

---

### POST /chat/conversations/{conversation}/end
End the conversation (driver or admin).

---

### GET /chat/unread-count
Get total unread message count across all conversations (for badge).

---

## 19. Ratings & Reviews

**Requires token.**

### GET /reviews
List reviews. Filterable by order, driver, or service.

**Query params:** `order_id`, `driver_id`, `service_id`, `rating`

---

### POST /reviews
Submit a review after an order is completed.

```json
{
  "order_id": 10,
  "driver_id": 3,
  "service_id": 1,
  "rating": 5,
  "review": "Very fast delivery!",
  "tags": ["fast", "friendly"]
}
```

Rating: 1-5 stars.

---

### GET /reviews/my-reviews
Get all reviews submitted by the logged-in user.

---

### GET /reviews/statistics
Get aggregate rating statistics (average, distribution).

---

### GET /reviews/{id}
Get a single review.

---

### PUT /reviews/{id}
Update your own review (within edit window).

---

## 20. Support Tickets

**Requires token.**

### GET /support/tickets
List support tickets. Customers see their own; admins see all.

**Query params:** `status` (open | in_progress | resolved | closed), `priority`

---

### POST /support/tickets
Create a new support ticket.

```json
{
  "subject": "My order was not delivered",
  "description": "I waited 2 hours but the driver never arrived.",
  "category": "delivery_issue",
  "priority": "high",
  "order_id": 10
}
```

Categories: `delivery_issue` | `payment_issue` | `driver_behavior` | `app_bug` | `other`
Priority: `low` | `medium` | `high` | `urgent`

---

### GET /support/tickets/{id}
Get ticket with all messages.

---

### POST /support/tickets/{id}/messages
Add a reply to a ticket.

```json
{ "message": "I have attached a screenshot of the issue." }
```

---

### POST /support/tickets/{id}/close
Close a resolved ticket.

---

### POST /support/tickets/{id}/satisfaction
Rate your support experience after ticket is closed.

```json
{ "rating": 5, "feedback": "Very helpful!" }
```

---

### GET /support/tickets/statistics
Ticket statistics (admin).

---

## 21. FAQ & Help Center

**Requires token.**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/faq/categories` | List all FAQ categories |
| GET | `/faq/categories/{id}` | Get articles in a category |
| GET | `/faq/articles/{id}` | Get a single article |
| GET | `/faq/search?q=keyword` | Search articles |
| GET | `/faq/featured` | Get featured articles |
| GET | `/faq/popular` | Get most-viewed articles |
| GET | `/faq/statistics` | FAQ usage stats (admin) |
| POST | `/faq/articles/{id}/helpful` | Mark article as helpful |
| POST | `/faq/articles/{id}/not-helpful` | Mark article as not helpful |

---

## 22. Document Verification

Users submit documents for admin review before they can be activated. **Requires token.**

### Document Verification Flow
```
User submits document  ?  Status: pending
Admin starts review    ?  Status: under_review
Admin approves/rejects ?  Status: approved / rejected
User can resubmit if rejected
```

### GET /documents/types
Get list of required document types.

**Response**
```json
[
  { "type": "drivers_license", "label": "Driver's License", "required": true },
  { "type": "vehicle_registration", "label": "Vehicle Registration", "required": true },
  { "type": "profile_photo", "label": "Profile Photo", "required": true }
]
```

---

### POST /documents/submit
Submit a document for verification.

```json
{
  "document_type": "drivers_license",
  "file_url": "https://storage.example.com/docs/license.jpg",
  "expiry_date": "2028-12-31",
  "notes": "Front side of license"
}
```

---

### GET /documents/my-documents
Get all documents submitted by the logged-in user with their statuses.

---

### GET /documents/verification-status
Get overall verification status — whether the user is fully verified.

**Response**
```json
{
  "is_verified": false,
  "pending_count": 1,
  "approved_count": 2,
  "rejected_count": 0,
  "required_documents": ["vehicle_registration"]
}
```

---

### GET /documents/{id}
Get a specific document submission.

---

### POST /documents/{id}/resubmit
Resubmit a rejected document with a new file.

```json
{
  "file_url": "https://storage.example.com/docs/license-new.jpg",
  "notes": "Updated photo with better lighting"
}
```

---

## 23. Admin Document Review

**Admin only. Requires token.**

### GET /admin/documents
List all submitted documents with filtering.

**Query params:** `status`, `document_type`, `user_id`

---

### GET /admin/documents/statistics
Document verification statistics and queue overview.

---

### GET /admin/documents/users-with-pending
List users who have pending documents awaiting review.

---

### POST /admin/documents/bulk-approve
Approve multiple documents at once.

```json
{ "document_ids": [1, 2, 3], "notes": "All verified" }
```

---

### GET /admin/documents/{id}
Get document details with user info.

---

### POST /admin/documents/{id}/start-review
Mark document as under review (locks it for this admin).

---

### POST /admin/documents/{id}/approve
Approve a document.

```json
{ "notes": "Document is valid and clear" }
```

---

### POST /admin/documents/{id}/reject
Reject a document with a reason.

```json
{
  "rejection_reason": "Image is blurry, cannot read details",
  "notes": "Please resubmit with a clearer photo"
}
```

---

### GET /admin/documents/user/{user}/history
Get full document submission history for a specific user.

---

## 24. Social Media Sharing

**Requires token** (except public endpoints).

### GET /social/platforms
Get list of supported social platforms and their config.

---

### GET /social/platforms/{platform}/guidelines
Get content guidelines for a specific platform (character limits, image sizes, etc).

---

### POST /social/orders/{order}/share
Generate a shareable link for a completed order.

```json
{
  "platform": "facebook",
  "message": "Just got my delivery in 20 minutes!"
}
```

**Response includes** `share_url` and `og_data` (Open Graph metadata for previews).

---

### POST /social/referral/share
Generate a referral share link.

```json
{ "platform": "whatsapp" }
```

---

### POST /social/milestone/share
Share a milestone (e.g. 100th order, 5-star streak).

```json
{
  "milestone_type": "orders_completed",
  "milestone_value": 100,
  "platform": "instagram"
}
```

---

### GET /social/my-shares
Get all shares created by the logged-in user.

---

### GET /social/analytics
Get engagement analytics for your shares (clicks, views).

---

### POST /social/shares/{share}/track
Track an engagement event on a share (click, view, etc).

```json
{ "event_type": "click" }
```

---

### Public Endpoints (no token)
| Endpoint | Description |
|----------|-------------|
| GET `/social/shares/{share}/public` | Get public share landing page data |
| GET `/social/trending` | Get trending shared content |
| GET `/social/statistics` | Get platform-wide sharing stats |

---

## 25. Geofencing

Location-based zones that trigger automatic notifications. **Requires token.**

### Geofence Types
| Type | Description |
|------|-------------|
| `delivery_zone` | Area where deliveries are available |
| `service_area` | General service coverage area |
| `restricted_area` | No-go zones |
| `pickup_zone` | Designated pickup locations |
| `partner_location` | Partner store boundaries |

### Trigger Types
- `enter` — User/driver enters the zone
- `exit` — User/driver leaves the zone
- `dwell` — User/driver stays in zone for X minutes

---

### GET /geofences
List all geofences.

**Query params:** `type`, `is_active`

---

### POST /geofences
Create a geofence (admin).

```json
{
  "name": "Manila Delivery Zone",
  "type": "delivery_zone",
  "shape": "circle",
  "center_latitude": 14.5995,
  "center_longitude": 120.9842,
  "radius_meters": 5000,
  "trigger_on": ["enter", "exit"],
  "notification_message": "You are now in our delivery zone!",
  "is_active": true
}
```

For polygon shapes, use `coordinates` array instead of center/radius.

---

### GET /geofences/types
Get available geofence types.

---

### GET /geofences/nearby
Get geofences near a location.

**Query params:** `latitude`, `longitude`, `radius_km`

---

### GET /geofences/statistics
Geofence trigger statistics (admin).

---

### GET /geofences/{id}
Get geofence details.

---

### PUT /geofences/{id}
Update a geofence.

---

### DELETE /geofences/{id}
Delete a geofence.

---

### POST /geofences/{id}/toggle
Enable or disable a geofence.

---

### GET /geofences/{id}/events
Get all trigger events for a geofence.

---

### POST /geofences/location/check
Check if a coordinate is inside any geofences.

```json
{
  "latitude": 14.5995,
  "longitude": 120.9842
}
```

**Response**
```json
{
  "inside_geofences": [
    { "id": 1, "name": "Manila Delivery Zone", "type": "delivery_zone" }
  ]
}
```

---

### POST /geofences/location/process
Process a location update and trigger any geofence events (used by driver app).

```json
{
  "latitude": 14.5995,
  "longitude": 120.9842,
  "user_id": 5,
  "order_id": 10
}
```

---

## 26. Analytics

Business intelligence dashboard. **Requires token (admin/partner_admin).**

### GET /analytics/dashboard
Full analytics overview — revenue, orders, drivers, customers.

**Query params:** `period` (today | week | month | year), `from`, `to`

---

### GET /analytics/revenue
Revenue breakdown with trends and forecasting.

**Query params:** `period`, `group_by` (day | week | month)

---

### GET /analytics/drivers/performance
Driver performance metrics — trips, ratings, on-time rate, earnings.

**Query params:** `driver_id`, `period`

---

### GET /analytics/customers/behavior
Customer behavior analysis — order frequency, preferred services, churn risk.

---

### GET /analytics/services/demand
Service demand patterns — peak hours, popular routes, seasonal trends.

---

### GET /analytics/geofences/effectiveness
Geofence ROI and trigger analytics.

---

### GET /analytics/predictions
Predictive analytics — demand forecasting, revenue projections.

**Query params:** `forecast_days` (default 30)

---

### GET /analytics/events
Raw analytics events log.

**Query params:** `event_type`, `from`, `to`, `per_page`

---

### POST /analytics/events/track
Track a custom analytics event from the frontend.

```json
{
  "event_type": "page_view",
  "event_category": "navigation",
  "properties": {
    "page": "order_history",
    "duration_seconds": 45
  }
}
```

---

### POST /analytics/export
Export analytics data as CSV/JSON.

```json
{
  "type": "revenue",
  "format": "csv",
  "from": "2026-01-01",
  "to": "2026-04-09"
}
```

---

## 27. Security & 2FA

**Requires token.**

### GET /security/dashboard
Security overview — recent events, failed logins, blocked IPs (admin).

---

### Two-Factor Authentication (2FA)

#### Setup Flow
```
1. POST /security/2fa/setup     ?  get QR code + secret
2. User scans QR in authenticator app (Google Authenticator, Authy)
3. POST /security/2fa/verify    ?  confirm with 6-digit code
4. 2FA is now active
```

#### POST /security/2fa/setup
Initiate 2FA setup.

**Response**
```json
{
  "secret": "JBSWY3DPEHPK3PXP",
  "qr_code_url": "otpauth://totp/LeSGo:juan@example.com?secret=...",
  "backup_codes": ["ABCD1234", "EFGH5678", ...]
}
```

Show the `qr_code_url` as a QR code image. Save the `backup_codes` — they are shown only once.

---

#### POST /security/2fa/verify
Confirm 2FA setup with a code from the authenticator app.

```json
{ "code": "123456" }
```

---

#### POST /security/2fa/disable
Disable 2FA for the account.

---

#### POST /security/2fa/backup-codes/regenerate
Generate new backup codes (invalidates old ones).

---

### Biometric Authentication

#### POST /security/biometric/enroll
Register a biometric method for a device.

```json
{
  "biometric_type": "fingerprint",
  "device_id": "device-uuid-here",
  "biometric_template": "hashed-template-string",
  "public_key": "-----BEGIN PUBLIC KEY-----...",
  "device_info": {
    "model": "iPhone 15",
    "os": "iOS 17"
  }
}
```

Biometric types: `fingerprint` | `face_id` | `voice` | `iris`

---

#### POST /security/biometric/verify
Verify biometric authentication.

```json
{
  "biometric_type": "fingerprint",
  "device_id": "device-uuid-here",
  "biometric_template": "hashed-template-string"
}
```

---

#### GET /security/biometric/list
List enrolled biometric methods for the user.

---

#### POST /security/biometric/deactivate
Remove a biometric enrollment.

```json
{ "biometric_id": 1 }
```

---

### Audit & Security Events

#### GET /security/audit/logs
Get audit log (admin). Filterable by user, event type, risk level, date range.

**Query params:** `user_id`, `event_type`, `risk_level` (low|medium|high|critical), `from_date`, `to_date`

---

#### GET /security/audit/events
Get security events (admin).

**Query params:** `severity` (info|warning|error|critical), `is_resolved`

---

#### POST /security/audit/events/{id}/resolve
Mark a security event as resolved (admin).

```json
{ "resolution_notes": "False positive — verified with user" }
```

---

### GDPR Compliance

#### POST /security/gdpr/requests
Submit a GDPR data request.

```json
{
  "request_type": "access",
  "description": "I want to download all my personal data"
}
```

Request types: `access` | `portability` | `rectification` | `erasure` | `restriction`

> After submitting, the user receives a verification email. Once verified, the request is processed (usually within 30 days).

---

#### GET /security/gdpr/requests
Get the logged-in user's GDPR requests and their status.

---

### IP Management (Admin only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/security/ip/whitelist` | List whitelisted IPs |
| POST | `/security/ip/whitelist` | Add IP to whitelist |
| GET | `/security/ip/blacklist` | List blacklisted IPs |
| POST | `/security/ip/blacklist` | Block an IP |

**Block an IP:**
```json
{
  "ip_address": "192.168.1.100",
  "reason": "suspicious_activity",
  "description": "Repeated failed login attempts",
  "expires_at": "2026-05-01T00:00:00Z"
}
```

---

## 28. Checklist Templates

Used for LesBuy orders — predefined shopping lists. **Requires token.**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/checklist-templates` | List templates |
| POST | `/checklist-templates` | Create a template |

**Create template body**
```json
{
  "name": "Weekly Groceries",
  "items": [
    { "name": "Rice 5kg", "quantity": 1 },
    { "name": "Eggs (tray)", "quantity": 2 }
  ]
}
```

---

## 29. Distance Calculator

**Requires token.**

### GET /distance/calculate
Calculate distance and estimated fare between two points.

**Query params:** `from_lat`, `from_lng`, `to_lat`, `to_lng`, `service_id`

**Response**
```json
{
  "distance_meters": 5200,
  "distance_km": 5.2,
  "estimated_fare": 71.90,
  "estimated_duration_minutes": 18
}
```

---

### GET /distance/overall
Get overall distance statistics for the logged-in user.

---

## 30. Error Reference

### Common Error Codes

| HTTP | Code | Meaning | What to do |
|------|------|---------|------------|
| 401 | UNAUTHENTICATED | Token missing or expired | Redirect to login |
| 403 | FORBIDDEN | Not your resource or wrong role | Show "Access denied" |
| 404 | NOT_FOUND | Resource doesn't exist | Show 404 page |
| 409 | CONFLICT | Duplicate (e.g. payment exists) | Show conflict message |
| 422 | VALIDATION_ERROR | Invalid input | Show field errors |
| 429 | RATE_LIMIT_EXCEEDED | Too many requests | Show retry countdown |
| 500 | SERVER_ERROR | Something broke on our end | Show generic error |

### Validation Error Format
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

Loop through `errors` to show field-level messages in your form.

---

## Quick Reference — All Endpoints

### Public (no token)
| Method | Endpoint |
|--------|----------|
| GET | `/ping` |
| POST | `/auth/register` |
| POST | `/auth/login` |
| GET | `/services` |
| GET | `/services/{id}` |
| POST | `/drivers/register` |
| POST | `/webhooks/payments/{provider}` |
| GET | `/social/shares/{share}/public` |
| GET | `/social/trending` |
| GET | `/social/statistics` |

### Auth Required
All other endpoints require `Authorization: Bearer {token}` header.

---

## Environment URLs

| Environment | Base URL |
|-------------|----------|
| Production | `https://lesgo-api-feature-auth-secmes.free.laravel.cloud/api/v1` |
| Local | `http://localhost:8000/api/v1` |

---

*Last updated: April 2026 — LeSGo API v1*
