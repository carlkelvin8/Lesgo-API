# LeSGo API - Complete Swagger/OpenAPI Specification Guide

## How to Access Swagger UI

### Local Development:
```
http://localhost:8000/api/documentation
```

### Production:
```
https://api.lesgo.ph/api/documentation
```

---

## Swagger Configuration

The Swagger UI is configured to:
- ✅ Show all API endpoints
- ✅ Include complete request/response schemas
- ✅ Support Bearer token authentication (Sanctum)
- ✅ Dark mode available
- ✅ Filter endpoints by tag
- ✅ Expand all operations by default

---

## All Endpoints Summary (100+ Endpoints)

### Health & Monitoring (4 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ping` | Simple health check |
| GET | `/health` | Comprehensive health with all dependencies |
| GET | `/health/liveness` | Liveness probe |
| GET | `/health/readiness` | Readiness probe |

### Authentication (7 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Login and get token |
| GET | `/auth/me` | Get authenticated user |
| PUT | `/auth/me` | Update profile |
| POST | `/auth/logout` | Logout current session |
| POST | `/auth/logout-all` | Logout from all devices |
| POST | `/auth/fcm-token` | Register FCM push token |

### Users (5 endpoints - Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/users` | List users |
| POST | `/users` | Create user |
| GET | `/users/{id}` | Get user |
| PATCH | `/users/{id}` | Update user |
| DELETE | `/users/{id}` | Delete user |

### Services (2 endpoints - Public)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/services` | List services |
| GET | `/services/{id}` | Get service details |

### Partners & Branches (7 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/partners` | List partners |
| GET | `/partners/{id}` | Get partner details |
| GET | `/partners/{partner_id}/branches` | List branches |
| POST | `/partners/{partner_id}/branches` | Create branch |
| PATCH | `/branches/{id}` | Update branch |
| DELETE | `/branches/{id}` | Delete branch |

### Addresses (4 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/users/{user_id}/addresses` | List addresses |
| POST | `/users/{user_id}/addresses` | Create address |
| PATCH | `/addresses/{id}` | Update address |
| DELETE | `/addresses/{id}` | Delete address |

### Orders (4 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/orders` | List orders |
| POST | `/orders` | Create order |
| GET | `/orders/{id}` | Get order details |
| PATCH | `/orders/{id}/status` | Update order status |

### Order Estimates (1 endpoint)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/orders/estimate` | Get fare estimate |

### Order Tracking (4 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tracking/orders/{id}` | Track order |
| GET | `/tracking/orders/{id}/location` | Get live location |
| POST | `/tracking/orders/{id}/events` | Add tracking event |
| POST | `/tracking/orders/multiple` | Track multiple orders |

### Live Tracking (6 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/tracking/driver/location` | Update driver location |
| GET | `/tracking/driver/{id}/location` | Get driver location |
| GET | `/tracking/driver/{id}/history` | Get location history |
| GET | `/tracking/order/{id}/live` | Get live order tracking |
| GET | `/tracking/drivers/nearby` | Get nearby drivers |
| GET | `/tracking/stats` | Get tracking stats |

### Payments (4 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/payments` | List payments |
| POST | `/payments` | Create payment |
| GET | `/payments/{id}` | Get payment details |
| POST | `/webhooks/payments/{provider}` | Payment webhooks |

### Payment Gateway (4 endpoints - Xendit)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/gateway/invoice` | Create invoice |
| GET | `/gateway/invoice/{id}` | Get invoice |
| POST | `/gateway/invoice/{id}/expire` | Expire invoice |
| POST | `/gateway/refund` | Process refund |

### Wallet (6 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wallets/{user_id}` | Get user wallet |
| GET | `/wallets/{user_id}/transactions` | Get transactions |
| GET | `/wallets/my/wallet` | Get my wallet |
| GET | `/wallets/my/transactions` | Get my transactions |
| GET | `/wallets/my/validation` | Get wallet validation |
| GET | `/wallets/threshold` | Get threshold |

### Wallet Settings (2 endpoints - Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/wallet-settings/threshold` | Get threshold |
| PUT | `/admin/wallet-settings/threshold` | Update threshold |

### Vouchers (2 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/vouchers/available` | Get available vouchers |
| POST | `/vouchers/validate` | Validate voucher |

### Drivers (5 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/drivers` | List drivers |
| GET | `/drivers/{id}` | Get driver details |
| PATCH | `/drivers/{id}/status` | Update driver status |
| PATCH | `/drivers/{id}/location` | Update driver location |
| POST | `/drivers/register` | Register driver |

### Notifications (4 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notifications` | List notifications |
| GET | `/notifications/unread-count` | Get unread count |
| PATCH | `/notifications/{id}/read` | Mark as read |
| POST | `/notifications/read-all` | Mark all as read |

### Receipts (1 endpoint)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/orders/{id}/receipt` | Get order receipt |

### Distance (2 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/distance/calculate` | Calculate distance |
| GET | `/distance/overall` | Get overall stats |

### Checklist Templates (2 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/checklist-templates` | List templates |
| POST | `/checklist-templates` | Create template |

### Reviews & Ratings (6 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reviews` | List reviews |
| POST | `/reviews` | Create review |
| GET | `/reviews/my-reviews` | Get my reviews |
| GET | `/reviews/statistics` | Get statistics |
| GET | `/reviews/{id}` | Get review |
| PUT | `/reviews/{id}` | Update review |

### Support Tickets (7 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/support/tickets` | List tickets |
| POST | `/support/tickets` | Create ticket |
| GET | `/support/tickets/statistics` | Get statistics |
| GET | `/support/tickets/{id}` | Get ticket |
| POST | `/support/tickets/{id}/messages` | Add message |
| POST | `/support/tickets/{id}/close` | Close ticket |
| POST | `/support/tickets/{id}/satisfaction` | Rate satisfaction |

### FAQ (9 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/faq/categories` | Get categories |
| GET | `/faq/categories/{id}` | Get category articles |
| GET | `/faq/articles/{id}` | Get article |
| GET | `/faq/search` | Search FAQ |
| GET | `/faq/featured` | Get featured |
| GET | `/faq/popular` | Get popular |
| GET | `/faq/statistics` | Get statistics |
| POST | `/faq/articles/{id}/helpful` | Mark helpful |
| POST | `/faq/articles/{id}/not-helpful` | Mark not helpful |

### Chat (7 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/chat/conversations` | List conversations |
| GET | `/chat/conversations/order/{id}` | Get/create conversation |
| GET | `/chat/conversations/{id}/messages` | Get messages |
| POST | `/chat/conversations/{id}/messages` | Send message |
| POST | `/chat/conversations/{id}/system-message` | Send system message |
| POST | `/chat/conversations/{id}/end` | End conversation |
| GET | `/chat/unread-count` | Get unread count |

### Social Media (11 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/social/shares/{id}/public` | Public share |
| GET | `/social/trending` | Trending |
| GET | `/social/statistics` | Statistics |
| GET | `/social/platforms` | Platforms |
| GET | `/social/platforms/{id}/guidelines` | Guidelines |
| POST | `/social/orders/{id}/share` | Share order |
| POST | `/social/referral/share` | Referral share |
| POST | `/social/milestone/share` | Milestone share |
| GET | `/social/my-shares` | My shares |
| GET | `/social/analytics` | Analytics |
| POST | `/social/shares/{id}/track` | Track engagement |

### Geofences (12 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/geofences` | List geofences |
| POST | `/geofences` | Create geofence |
| GET | `/geofences/types` | Get types |
| GET | `/geofences/nearby` | Get nearby |
| GET | `/geofences/statistics` | Get statistics |
| GET | `/geofences/{id}` | Get details |
| PUT | `/geofences/{id}` | Update |
| DELETE | `/geofences/{id}` | Delete |
| POST | `/geofences/{id}/toggle` | Toggle |
| GET | `/geofences/{id}/events` | Get events |
| POST | `/geofences/location/check` | Check location |
| POST | `/geofences/location/process` | Process location |

### Realtime (9 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/realtime/connect` | Connect |
| POST | `/realtime/disconnect` | Disconnect |
| POST | `/realtime/ping` | Ping |
| GET | `/realtime/connections` | Get connections |
| GET | `/realtime/notifications` | Get notifications |
| POST | `/realtime/notifications/{id}/read` | Mark read |
| POST | `/realtime/notifications/read-all` | Mark all read |
| GET | `/realtime/stats` | Get stats |
| POST | `/realtime/test-notification` | Test notification |

### Analytics (10 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/analytics/dashboard` | Dashboard |
| GET | `/analytics/revenue` | Revenue |
| GET | `/analytics/drivers/performance` | Driver performance |
| GET | `/analytics/customers/behavior` | Customer behavior |
| GET | `/analytics/services/demand` | Service demand |
| GET | `/analytics/geofences/effectiveness` | Geofence analytics |
| GET | `/analytics/predictions` | Predictions |
| GET | `/analytics/events` | Get events |
| POST | `/analytics/events/track` | Track event |
| POST | `/analytics/export` | Export |

### Security (14 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/security/dashboard` | Security dashboard |
| POST | `/security/2fa/setup` | Setup 2FA |
| POST | `/security/2fa/verify` | Verify 2FA |
| POST | `/security/2fa/disable` | Disable 2FA |
| POST | `/security/2fa/backup-codes/regenerate` | Regenerate codes |
| POST | `/security/biometric/enroll` | Enroll biometric |
| POST | `/security/biometric/verify` | Verify biometric |
| GET | `/security/biometric/list` | List biometrics |
| POST | `/security/biometric/deactivate` | Deactivate |
| GET | `/security/audit/logs` | Get audit logs |
| GET | `/security/audit/events` | Get security events |
| POST | `/security/audit/events/{id}/resolve` | Resolve event |
| POST | `/security/gdpr/requests` | Create GDPR request |
| GET | `/security/gdpr/requests` | Get GDPR requests |

### Documents (6 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/documents/submit` | Submit document |
| GET | `/documents/my-documents` | Get my documents |
| GET | `/documents/types` | Get document types |
| GET | `/documents/verification-status` | Get status |
| GET | `/documents/{id}` | Get document |
| POST | `/documents/{id}/resubmit` | Resubmit |

### Admin - Documents (9 endpoints)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/documents` | List all documents |
| GET | `/admin/documents/statistics` | Get statistics |
| GET | `/admin/documents/users-with-pending` | Users with pending |
| POST | `/admin/documents/bulk-approve` | Bulk approve |
| GET | `/admin/documents/user/{id}/history` | User history |
| GET | `/admin/documents/{id}` | Get document |
| POST | `/admin/documents/{id}/start-review` | Start review |
| POST | `/admin/documents/{id}/approve` | Approve |
| POST | `/admin/documents/{id}/reject` | Reject |

---

## Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {your-token-here}
```

### How to Get Token

1. **Register**: `POST /api/v1/auth/register`
2. **Login**: `POST /api/v1/auth/login`

Both return:
```json
{
  "success": true,
  "token": "1|abc123...",
  "user": { ... }
}
```

---

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error"]
  }
}
```

---

## Rate Limiting

| Endpoint Group | Limit |
|---------------|-------|
| Auth (login/register) | 10 requests/minute |
| Public API | 60 requests/minute |
| Authenticated API | 120 requests/minute |
| Driver registration | 5 requests/hour |

---

## Testing Swagger UI

### 1. Start Laravel Server:
```bash
php artisan serve
```

### 2. Open Swagger UI:
```
http://localhost:8000/api/documentation
```

### 3. Authenticate:
- Click "Authorize" button
- Enter token: `Bearer {your-token}`
- Click "Authorize"

### 4. Test Endpoints:
- Click on any endpoint to expand
- Click "Try it out"
- Fill in parameters
- Click "Execute"
- View response

---

## Complete API Documentation

For complete API documentation with all request/response examples, see:
- **API_DOCUMENTATION.md** - Complete markdown documentation
- **Swagger UI** - Interactive API documentation at `/api/documentation`

---

## Common Issues

### Issue: Swagger UI not accessible
**Solution**: Ensure `l5-swagger` package is installed and routes are registered.

### Issue: Endpoints not showing
**Solution**: Check `config/l5-swagger.php` exclude patterns.

### Issue: Authentication not working in Swagger UI
**Solution**: Click "Authorize" and enter `Bearer {token}` format.
