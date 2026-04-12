# LeSGo API - Enhanced Security Features Implementation

## Overview

The LeSGo API now has comprehensive enhanced security features including API versioning, request signature verification, enhanced audit logging, device fingerprinting, and anomaly detection for fraud prevention.

---

## 1. API Versioning Strategy

### 1.1 How It Works

The API supports semantic versioning with automatic version detection from:
- **URL prefix**: `/api/v1/orders`
- **Accept header**: `application/vnd.lesgo.v1+json`
- **Default**: Latest version (v1) if not specified

### 1.2 Supported Versions

| Version | Status | End-of-Life Date |
|---------|--------|------------------|
| v1 | ✅ Active | N/A |

### 1.3 Usage Examples

**Via URL:**
```bash
GET /api/v1/orders
GET /api/v1/users/123
```

**Via Accept Header:**
```bash
GET /api/v1/orders
Accept: application/vnd.lesgo.v1+json
```

**Response Headers:**
```
X-API-Version: v1
X-API-Latest-Version: v1
```

**Deprecation Warning (for old versions):**
```
X-API-Deprecation: API version v1 is deprecated. Please upgrade to v2
X-API-Deprecation-Date: 2026-12-31
```

### 1.4 Version Migration Strategy

When introducing v2:
1. Add v2 to `SUPPORTED_VERSIONS` array
2. Move v1 to `DEPRECATED_VERSIONS` array
3. Set deprecation date in config
4. Update `LATEST_VERSION` constant
5. Clients receive deprecation warnings automatically

---

## 2. Request Signature Verification

### 2.1 Purpose

Prevents request tampering and ensures request integrity for sensitive operations using HMAC-SHA256 signatures.

### 2.2 How It Works

1. Client generates a unique nonce (UUID)
2. Client creates timestamp (Unix epoch)
3. Client generates HMAC-SHA256 signature from request components
4. Server verifies signature, timestamp, and nonce
5. Server rejects if: signature invalid, timestamp expired, or nonce reused

### 2.3 Signature Generation (Client-Side)

**JavaScript/Node.js Example:**
```javascript
const crypto = require('crypto');

function generateSignature(method, path, body, secret) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomUUID();
    
    const signatureString = `${method.toUpperCase()}\n${path}\n${timestamp}\n${nonce}\n${body || ''}`;
    const signature = crypto.createHmac('sha256', secret).update(signatureString).digest('hex');
    
    return {
        'X-Signature': signature,
        'X-Timestamp': timestamp,
        'X-Nonce': nonce,
    };
}

// Usage
const headers = generateSignature(
    'POST',
    '/api/v1/orders',
    JSON.stringify({ service_id: 1, pickup: {...}, dropoff: {...} }),
    'your-secret-key'
);

const response = await fetch('https://api.lesgo.ph/api/v1/orders', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_TOKEN',
        ...headers,
    },
    body: JSON.stringify(orderData),
});
```

**PHP Example:**
```php
function generateSignature($method, $path, $body, $secret) {
    $timestamp = time();
    $nonce = bin2hex(random_bytes(16));
    
    $signatureString = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . ($body ?: '');
    $signature = hash_hmac('sha256', $signatureString, $secret);
    
    return [
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'X-Nonce' => $nonce,
    ];
}

// Usage
$headers = generateSignature('POST', '/api/v1/orders', json_encode($orderData), 'your-secret-key');

$ch = curl_init('https://api.lesgo.ph/api/v1/orders');
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
    'Content-Type: application/json',
    'Authorization: Bearer YOUR_TOKEN',
], array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers)));
```

**Python Example:**
```python
import hmac
import hashlib
import time
import uuid

def generate_signature(method, path, body, secret):
    timestamp = str(int(time.time()))
    nonce = str(uuid.uuid4())
    
    signature_string = f"{method.upper()}\n{path}\n{timestamp}\n{nonce}\n{body or ''}"
    signature = hmac.new(secret.encode(), signature_string.encode(), hashlib.sha256).hexdigest()
    
    return {
        'X-Signature': signature,
        'X-Timestamp': timestamp,
        'X-Nonce': nonce,
    }

# Usage
import requests

headers = generate_signature('POST', '/api/v1/orders', json.dumps(order_data), 'your-secret-key')
headers['Authorization'] = 'Bearer YOUR_TOKEN'
headers['Content-Type'] = 'application/json'

response = requests.post('https://api.lesgo.ph/api/v1/orders', json=order_data, headers=headers)
```

### 2.4 Protected Endpoints

Apply signature verification to:
- Financial transactions (payments, wallet operations)
- Order creation and modifications
- Admin operations
- User data modifications

### 2.5 Security Features

- **Replay Attack Prevention**: Nonces are cached for 5 minutes
- **Timestamp Validation**: Requests older than 5 minutes are rejected
- **HMAC-SHA256**: Cryptographically secure signature verification
- **Automatic Logging**: Failed signature attempts logged as security events

---

## 3. Enhanced Audit Logging

### 3.1 Compliance Standards

The audit logging system supports:
- **SOX** (Sarbanes-Oxley): Financial transaction logging
- **GDPR**: Data subject access and modification tracking
- **PCI-DSS**: Payment card industry compliance
- **HIPAA**: Healthcare data protection (if applicable)

### 3.2 Log Categories

| Category | Description | Retention |
|----------|-------------|-----------|
| Authentication | Login, logout, 2FA, biometric | 7 years |
| Authorization | Permission changes | 7 years |
| Data Access | Sensitive data reads | 1 year |
| Data Modification | Create, update, delete | 7 years |
| Financial | Payments, wallet transactions | 10 years |
| Administrative | Admin actions | 7 years |
| Security | Security events, alerts | 10 years |
| Compliance | GDPR requests, audits | 10 years |

### 3.3 Usage Examples

**Basic Audit Log:**
```php
use App\Services\AuditLogger;

AuditLogger::log(
    'user_profile_updated',
    AuditLogger::CATEGORY_DATA_MODIFICATION,
    'update',
    'User',
    $user->id,
    $oldData,
    $newData,
    $user->id,
    $request,
    'low'
);
```

**Authentication Logging:**
```php
// Successful login
AuditLogger::logAuth('login', $user->id, true);

// Failed login
AuditLogger::logAuth('login', null, false);
```

**Financial Transaction Logging:**
```php
AuditLogger::logFinancialTransaction(
    'payment_created',
    'Payment',
    $payment->id,
    [
        'amount' => 85.50,
        'currency' => 'PHP',
        'payment_method' => 'gcash',
        'order_id' => $order->id,
    ],
    $user->id
);
```

**GDPR Compliance Logging:**
```php
AuditLogger::logGDPR(
    'data_export',
    $user->id,
    [
        'legal_basis' => 'consent',
        'data_types' => ['profile', 'orders', 'payments'],
        'export_format' => 'json',
    ]
);
```

**Security Event Logging:**
```php
AuditLogger::logSecurity(
    'unauthorized_access_attempt',
    'high',
    [
        'description' => 'User attempted to access admin endpoint',
        'endpoint' => '/api/v1/admin/users',
        'user_role' => 'customer',
    ],
    $user->id
);
```

### 3.4 Compliance Reports

**Generate SOX Compliance Report:**
```php
$report = AuditLogger::getComplianceReport(
    'SOX',
    '2026-01-01',
    '2026-12-31',
    $userId // optional
);
```

**Export Audit Logs:**
```php
$export = AuditLogger::exportLogs(
    '2026-01-01',
    '2026-12-31',
    'json',
    $userId
);

// Save to file
file_put_contents('audit-export.json', json_encode($export, JSON_PRETTY_PRINT));
```

### 3.5 Automated Cleanup

Audit logs are automatically cleaned based on retention policy:

```bash
# Run via cron job (monthly)
0 0 1 * * php /path/to/lesgo-api/artisan schedule:run
```

In `App\Console\Kernel`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        AuditLogger::cleanupOldLogs();
    })->monthly();
}
```

---

## 4. Device Fingerprinting

### 4.1 How It Works

The device fingerprinting system creates unique device identifiers by combining:
- User-Agent string
- Browser/Platform headers
- Screen resolution
- Timezone
- Canvas/WebGL fingerprints (browser)
- Device model (mobile)
- IP address

### 4.2 Client-Side Implementation

**Browser (JavaScript):**
```javascript
async function getDeviceFingerprint() {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.textBaseline = 'top';
    ctx.font = '14px Arial';
    ctx.fillText('Device Fingerprint', 2, 2);
    const canvasFingerprint = canvas.toDataURL();

    return {
        'X-Screen-Resolution': `${screen.width}x${screen.height}`,
        'X-Timezone': Intl.DateTimeFormat().resolvedOptions().timeZone,
        'X-Platform': navigator.platform,
        'X-Browser': navigator.userAgent,
        'X-Canvas-Fingerprint': canvasFingerprint,
    };
}

// Add to all API requests
const deviceHeaders = await getDeviceFingerprint();

fetch('/api/v1/orders', {
    headers: {
        ...deviceHeaders,
        'Authorization': 'Bearer TOKEN',
    },
});
```

**React Native:**
```javascript
import { Platform, Dimensions } from 'react-native';
import DeviceInfo from 'react-native-device-info';

function getDeviceHeaders() {
    return {
        'X-Screen-Resolution': `${Dimensions.get('window').width}x${Dimensions.get('window').height}`,
        'X-Timezone': Intl.DateTimeFormat().resolvedOptions().timeZone,
        'X-Platform': Platform.OS,
        'X-Device-Model': DeviceInfo.getModel(),
        'X-OS': Platform.OS,
        'X-OS-Version': Platform.Version,
    };
}
```

### 4.3 Server-Side Usage

**Check if Device is Trusted:**
```php
use App\Services\DeviceFingerprintService;

$fingerprintService = app(DeviceFingerprintService::class);

$isTrusted = $fingerprintService->isDeviceTrusted($request, $user->id);

if (!$isTrusted) {
    // Require 2FA for untrusted devices
    return response()->json([
        'requires_2fa' => true,
        'message' => 'New device detected. Please verify with 2FA.',
    ], 403);
}
```

**Get User's Devices:**
```php
$devices = $fingerprintService->getUserDevices($user->id);

return response()->json([
    'devices' => $devices,
    'total_devices' => count($devices),
    'trusted_devices' => collect($devices)->where('is_trusted', true)->count(),
]);
```

**Trust Device After 2FA:**
```php
$fingerprintService->trustDevice($request, $user->id);
```

**Revoke Device:**
```php
// Revoke specific device
$fingerprintService->revokeDevice($request, $user->id);

// Revoke all devices (e.g., after password change)
$fingerprintService->revokeAllDevices($user->id);
```

### 4.4 Fraud Detection

The system automatically flags suspicious activity:
- **5+ devices** for same user → Flagged for review
- **Untrusted device** → Requires additional verification
- **Device change** → Triggers security notification

---

## 5. Anomaly Detection

### 5.1 Detection Rules

| Rule | Threshold | Severity | Action |
|------|-----------|----------|--------|
| Brute Force Login | 10+ failed attempts/hour | High | Block IP, notify user |
| Credential Stuffing | 5+ unique emails/hour | Critical | Block IP, create security event |
| Large Transaction | ≥ PHP 10,000 | Medium | Flag for review |
| Rapid Transactions | 5+/hour | High | Temporary cooldown |
| Excessive Orders | 20+/hour | High | Rate limit user |
| Impossible Travel | >200 km/h | Critical | Freeze account, verify identity |
| Wallet Abuse | 30+ transactions/hour | High | Temporary restriction |
| Money Laundering | Rapid top-up/withdrawal | Critical | Freeze, compliance review |

### 5.2 Real-Time Detection

**Login Anomaly Detection:**
```php
use App\Services\AnomalyDetectionService;

$anomalyService = app(AnomalyDetectionService::class);

$result = $anomalyService->checkLoginAnomaly($email, $request->ip(), $success);

if ($result['is_suspicious']) {
    foreach ($result['anomalies'] as $anomaly) {
        Log::warning("Login anomaly: {$anomaly['type']}", $anomaly);
    }
    
    if (collect($result['anomalies'])->contains('severity', 'critical')) {
        return response()->json([
            'success' => false,
            'message' => 'Suspicious activity detected. Please verify your identity.',
            'requires_verification' => true,
        ], 403);
    }
}
```

**Transaction Anomaly Detection:**
```php
$result = $anomalyService->checkTransactionAnomaly($userId, $amount, $paymentMethod);

if ($result['is_suspicious']) {
    // Require additional verification
    return response()->json([
        'success' => false,
        'message' => 'Transaction flagged for review. Additional verification required.',
        'anomalies' => $result['anomalies'],
    ], 403);
}
```

**Location Anomaly Detection (Impossible Travel):**
```php
$result = $anomalyService->checkLocationAnomaly($userId, $latitude, $longitude);

if ($result['is_suspicious']) {
    // Impossible travel detected
    $anomalyService->logSecurityEvent('impossible_travel', 'critical', [
        'user_id' => $userId,
        'description' => 'User appeared to travel at impossible speed',
        ...$result['anomalies'][0],
    ]);
    
    // Freeze account pending verification
    $user->update(['account_status' => 'frozen']);
}
```

### 5.3 User Risk Score

Calculate risk score (0-100) for users:

```php
$riskScore = $anomalyService->getUserRiskScore($userId);

if ($riskScore >= 70) {
    // High-risk user - require additional verification
    $requiresReview = true;
} elseif ($riskScore >= 40) {
    // Medium-risk user - monitor closely
    $monitoringLevel = 'elevated';
} else {
    // Low-risk user - normal operations
    $monitoringLevel = 'normal';
}
```

### 5.4 Anomaly Statistics

Get system-wide anomaly statistics:

```php
$stats = $anomalyService->getStatistics();

// Returns:
[
    'total_anomalies_today' => 15,
    'critical_events' => 3,
    'high_risk_users' => 8,
    'blocked_ips_today' => 12,
]
```

---

## 6. Integration Examples

### 6.1 Middleware Stack

Add to `app/Http/Kernel.php`:

```php
protected $routeMiddleware = [
    // ... existing middleware
    'api.version' => \App\Http\Middleware\ApiVersioning::class,
    'signature.verify' => \App\Http\Middleware\VerifyRequestSignature::class,
    'device.fingerprint' => \App\Http\Middleware\DeviceFingerprinting::class,
];

protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\ApiVersioning::class,
        \App\Http\Middleware\DeviceFingerprinting::class,
        // ... other middleware
    ],
];
```

### 6.2 Route Protection

**Require Signature for Sensitive Endpoints:**
```php
Route::middleware(['auth:sanctum', 'signature.verify'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/wallet/top-up', [WalletController::class, 'topUp']);
});
```

**Require Trusted Device:**
```php
Route::middleware(['auth:sanctum', 'device.fingerprint'])->group(function () {
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
});
```

### 6.3 Security Dashboard

Enhanced security controller with anomaly detection:

```php
// In SecurityController.php
public function dashboard(): JsonResponse
{
    $anomalyService = app(AnomalyDetectionService::class);
    $fingerprintService = app(DeviceFingerprintService::class);

    return response()->json([
        'success' => true,
        'data' => [
            'anomaly_statistics' => $anomalyService->getStatistics(),
            'active_security_events' => SecurityEvent::where('is_resolved', false)->count(),
            'critical_events' => SecurityEvent::where('severity', 'critical')
                ->where('is_resolved', false)
                ->count(),
            'blocked_ips' => IpBlacklist::count(),
            // ... existing data
        ],
    ]);
}
```

---

## 7. Configuration

### 7.1 Environment Variables

Add to `.env`:

```env
# Request Signature Verification
REQUEST_SIGNATURE_SECRET=your-secret-key-here

# API Versioning
API_DEPRECATION_DATES_v1=2026-12-31

# Anomaly Detection Thresholds
ANOMALY_MAX_LOGIN_ATTEMPTS_PER_HOUR=10
ANOMALY_MAX_FAILED_TRANSACTIONS_PER_HOUR=5
ANOMALY_MAX_ORDERS_PER_HOUR=20
ANOMALY_MAX_DISTANCE_KM_PER_HOUR=200
ANOMALY_SUSPICIOUS_AMOUNT_THRESHOLD=10000
```

### 7.2 Configuration File

Create `config/security.php`:

```php
return [
    'request_signature' => [
        'secret' => env('REQUEST_SIGNATURE_SECRET'),
        'max_age_seconds' => 300,
    ],
    
    'api_versioning' => [
        'supported_versions' => ['v1'],
        'deprecated_versions' => [],
        'latest_version' => 'v1',
    ],
    
    'anomaly_detection' => [
        'max_login_attempts_per_hour' => 10,
        'max_failed_transactions_per_hour' => 5,
        'max_orders_per_hour' => 20,
        'max_distance_km_per_hour' => 200,
        'suspicious_amount_threshold' => 10000,
    ],
    
    'device_fingerprinting' => [
        'cache_days' => 30,
        'max_devices_per_user' => 5,
    ],
    
    'audit_logging' => [
        'retention_critical' => 3650, // 10 years
        'retention_high' => 2555,     // 7 years
        'retention_medium' => 365,    // 1 year
        'retention_low' => 90,        // 90 days
    ],
];
```

---

## 8. Monitoring & Alerts

### 8.1 Log Channels

Add to `config/logging.php`:

```php
'channels' => [
    'audit' => [
        'driver' => 'daily',
        'path' => storage_path('logs/audit/audit.log'),
        'level' => 'info',
        'days' => 3650,
    ],
    
    'security' => [
        'driver' => 'daily',
        'path' => storage_path('logs/security/security.log'),
        'level' => 'warning',
        'days' => 365,
    ],
],
```

### 8.2 Alerting

Set up alerts for critical events:

```php
// In App\Providers\EventServiceProvider
Event::listen(function (SecurityEventCreated $event) {
    if ($event->event->severity === 'critical') {
        // Send email/SMS/Slack alert
        Notification::route('mail', 'security@lesgo.ph')
            ->notify(new CriticalSecurityEventAlert($event->event));
    }
});
```

---

## 9. Testing

### 9.1 Test Request Signature

```bash
# Generate signature
php artisan tinker

$secret = config('services.request_signature.secret');
$timestamp = time();
$nonce = bin2hex(random_bytes(16));
$signatureString = "POST\napi/v1/orders\n{$timestamp}\n{$nonce}\n{}";
$signature = hash_hmac('sha256', $signatureString, $secret);

# Test with curl
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-Signature: {$signature}" \
  -H "X-Timestamp: {$timestamp}" \
  -H "X-Nonce: {$nonce}" \
  -d '{}'
```

### 9.2 Test Anomaly Detection

```php
// Test brute force detection
$anomalyService = app(AnomalyDetectionService::class);

for ($i = 0; $i < 15; $i++) {
    $result = $anomalyService->checkLoginAnomaly(
        'test@example.com',
        '192.168.1.100',
        false
    );
}

// Should trigger brute_force anomaly
assert($result['is_suspicious'] === true);
```

---

## 10. Best Practices

1. **Always log financial transactions** with full details
2. **Use request signatures** for all write operations
3. **Monitor anomaly dashboard** daily
4. **Review critical security events** within 1 hour
5. **Rotate signature secrets** quarterly
6. **Export audit logs** before retention period expires
7. **Test device fingerprinting** across all client platforms
8. **Configure alerts** for critical anomalies
9. **Review and update** anomaly thresholds monthly
10. **Document all compliance** procedures for auditors

---

## Summary

✅ **API Versioning**: Semantic versioning with deprecation warnings  
✅ **Request Signatures**: HMAC-SHA256 verification for sensitive operations  
✅ **Enhanced Audit Logging**: SOX, GDPR, PCI-DSS, HIPAA compliance  
✅ **Device Fingerprinting**: Track and trust devices for fraud prevention  
✅ **Anomaly Detection**: Real-time detection of suspicious activities  

All security features are production-ready and fully integrated with the existing security infrastructure!
