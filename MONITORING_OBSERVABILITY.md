# LeSGo API - Monitoring & Observability Implementation

## Overview

The LeSGo API now has comprehensive monitoring and observability features including APM integration, structured logging with correlation IDs, health check endpoints, and business metrics dashboards for complete visibility into application performance and business health.

---

## 1. Application Performance Monitoring (APM) Integration

### 1.1 Supported APM Providers

- ✅ **New Relic** (PHP extension)
- ✅ **Datadog** (PHP tracer)
- ✅ **Sentry** (error tracking)
- ✅ **Elastic APM** (Elastic Stack)
- ✅ **Custom** (built-in metrics tracking)

### 1.2 Configuration

Add to `.env`:

```env
# APM Configuration
APM_PROVIDER=custom  # newrelic, datadog, sentry, elastic, custom
```

### 1.3 Usage Examples

**Service File:** `app/Services/ApmService.php`

**Automatic Request Monitoring:**
```php
use App\Services\ApmService;

$apmService = app(ApmService::class);

// Start monitoring (called automatically by middleware)
$apmService->startRequest($request);

// End monitoring (called automatically by middleware)
$metrics = $apmService->endRequest($response->getStatusCode());

// Returns:
[
    'request_id' => 'req_abc123',
    'method' => 'POST',
    'url' => 'https://api.lesgo.ph/api/v1/orders',
    'duration_ms' => 245.67,
    'memory_used_bytes' => 2048576,
    'peak_memory_bytes' => 4096123,
    'status_code' => 200,
    'has_error' => false,
]
```

**Track Custom Metrics:**
```php
// Track business metric
$apmService->trackMetric('order.value', 85.50, [
    'order_id' => $order->id,
    'service' => 'LESGO',
]);

// Track database query
$apmService->trackQuery('SELECT * FROM orders WHERE id = ?', 15.5, [123], true);

// Track cache operation
$apmService->trackCache('get', 'orders:user:123:list', 2.5, true);

// Track queue job
$apmService->trackJob('SendOrderConfirmationJob', 125.3, true, 'high');

// Track external API call
$apmService->trackExternalApi('xendit', '/v1/invoices', 450.2, 200, true);
```

**Record Exceptions:**
```php
try {
    // Your code
} catch (\Exception $e) {
    $apmService->recordException($e, [
        'user_id' => $user->id,
        'order_id' => $order->id,
    ]);
    
    throw $e;
}
```

**Get Performance Summary:**
```php
$summary = $apmService->getPerformanceSummary();

// Returns:
[
    'apm_provider' => 'custom',
    'request_count_last_minute' => 145,
    'average_response_time_ms' => 234.56,
    'error_rate_percent' => 2.3,
    'memory_usage_mb' => 45.67,
    'peak_memory_mb' => 78.90,
]
```

### 1.4 Provider-Specific Setup

**New Relic:**
```bash
# Install New Relic PHP extension
pecl install newrelic

# Configure in php.ini
extension=newrelic.so
newrelic.appname="LeSGo API"
```

```php
// In code
if (extension_loaded('newrelic')) {
    newrelic_add_custom_parameter('user_id', $user->id);
    newrelic_add_custom_parameter('order_id', $order->id);
}
```

**Sentry:**
```bash
composer require sentry/sentry-laravel
```

```env
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/your-project
```

**Datadog:**
```bash
# Install Datadog PHP tracer
curl -sS https://install.datadoghq.com/scripts/install_script_agent7.sh | bash
```

```env
DD_TRACE_ENABLED=true
DD_SERVICE=lesgo-api
DD_ENV=production
```

---

## 2. Structured Logging with Correlation IDs

### 2.1 Features

- ✅ **Automatic correlation ID** generation (UUID)
- ✅ **JSON structured logging** for all requests
- ✅ **Request/response logging** with performance metrics
- ✅ **Sensitive data sanitization** (passwords, tokens)
- ✅ **Multiple log channels** (structured, audit, security)

### 2.2 Middleware Configuration

Add to `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\StructuredLogging::class,
        // ... other middleware
    ],
];
```

Add to `config/logging.php`:

```php
'channels' => [
    'structured' => [
        'driver' => 'daily',
        'path' => storage_path('logs/structured/structured.log'),
        'level' => 'debug',
        'days' => 30,
        'formatter' => Monolog\Formatter\JsonFormatter::class,
    ],
    
    'audit' => [
        'driver' => 'daily',
        'path' => storage_path('logs/audit/audit.log'),
        'level' => 'info',
        'days' => 365,
    ],
    
    'security' => [
        'driver' => 'daily',
        'path' => storage_path('logs/security/security.log'),
        'level' => 'warning',
        'days' => 365,
    ],
],
```

### 2.3 Log Format

**Request Log:**
```json
{
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
    "event": "request_started",
    "timestamp": "2026-04-12T10:30:00.000000Z",
    "method": "POST",
    "url": "https://api.lesgo.ph/api/v1/orders",
    "path": "api/v1/orders",
    "ip": "192.168.1.100",
    "user_agent": "LeSGo Mobile/1.0.0",
    "user_id": 123,
    "query_params": {
        "service_id": 1,
        "password": "***REDACTED***"
    }
}
```

**Response Log:**
```json
{
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
    "event": "request_completed",
    "timestamp": "2026-04-12T10:30:00.245Z",
    "method": "POST",
    "path": "api/v1/orders",
    "status_code": 201,
    "duration_ms": 245.67,
    "memory_used_mb": 12.34,
    "peak_memory_mb": 15.67,
    "user_id": 123,
    "apm_metrics": {
        "request_id": "req_abc123",
        "duration_ms": 245.67,
        "memory_used_bytes": 2048576
    }
}
```

### 2.4 Response Headers

Every response includes correlation ID and performance headers:

```
X-Correlation-ID: 550e8400-e29b-41d4-a716-446655440000
X-Request-Id: 550e8400-e29b-41d4-a716-446655440000
X-Response-Time: 245.67ms
```

### 2.5 Using Correlation IDs

**From Client:**
```javascript
// Include correlation ID in all requests
const correlationId = '550e8400-e29b-41d4-a716-446655440000';

fetch('/api/v1/orders', {
    headers: {
        'X-Correlation-ID': correlationId,
        'X-Request-ID': correlationId,
    },
});

// Extract from response
const responseCorrelationId = response.headers.get('X-Correlation-ID');
console.log('Correlation ID:', responseCorrelationId);
```

**Search Logs by Correlation ID:**
```bash
# Find all logs for a specific request
grep "550e8400-e29b-41d4-a716-446655440000" storage/logs/structured/structured.log
```

---

## 3. Health Check Endpoints

### 3.1 Available Endpoints

| Endpoint | Purpose | HTTP Code | Response Time |
|----------|---------|-----------|---------------|
| `GET /api/v1/ping` | Simple liveness check | 200 | < 10ms |
| `GET /api/v1/health/liveness` | Application alive | 200 | < 10ms |
| `GET /api/v1/health/readiness` | Ready to accept traffic | 200/503 | < 50ms |
| `GET /api/v1/health` | Comprehensive health check | 200/503 | < 500ms |

### 3.2 Simple Ping

**GET** `/api/v1/ping`

**Response (200 OK):**
```json
{
    "status": "ok",
    "message": "LeSGo API v1 OK",
    "timestamp": "2026-04-12T10:30:00.000000Z",
    "environment": "production",
    "php_version": "8.3.0",
    "laravel_version": "11.0.0"
}
```

---

### 3.3 Liveness Check

**GET** `/api/v1/health/liveness`

**Purpose:** Kubernetes/Docker liveness probe - is the application process running?

**Response (200 OK):**
```json
{
    "alive": true,
    "timestamp": "2026-04-12T10:30:00.000000Z",
    "version": "1.0.0"
}
```

---

### 3.4 Readiness Check

**GET** `/api/v1/health/readiness`

**Purpose:** Load balancer readiness probe - can the application accept traffic?

**Response - Ready (200 OK):**
```json
{
    "ready": true,
    "database": "connected",
    "cache": "connected",
    "timestamp": "2026-04-12T10:30:00.000000Z"
}
```

**Response - Not Ready (503 Service Unavailable):**
```json
{
    "ready": false,
    "database": "disconnected",
    "cache": "connected",
    "timestamp": "2026-04-12T10:30:00.000000Z"
}
```

---

### 3.5 Comprehensive Health Check

**GET** `/api/v1/health`

**Purpose:** Detailed health check with all dependencies

**Response - Healthy (200 OK):**
```json
{
    "status": "healthy",
    "timestamp": "2026-04-12T10:30:00.000000Z",
    "version": "1.0.0",
    "environment": "production",
    "uptime_seconds": 86400,
    "checks": {
        "database": {
            "status": "healthy",
            "connection": "pgsql",
            "host": "primary-db.lesgo.internal",
            "duration_ms": 2.34,
            "connections": "192.168.1.100:5432",
            "last_check": "2026-04-12T10:30:00.000000Z"
        },
        "redis": {
            "status": "healthy",
            "driver": "redis",
            "duration_ms": 1.23,
            "used_memory": "125.45M",
            "connected_clients": 15,
            "last_check": "2026-04-12T10:30:00.000000Z"
        },
        "queue": {
            "status": "healthy",
            "connection": "redis",
            "failed_jobs_last_hour": 2,
            "queue_sizes": {
                "critical": 5,
                "high": 12,
                "medium": 45,
                "low": 123,
                "default": 0
            },
            "last_check": "2026-04-12T10:30:00.000000Z"
        },
        "storage": {
            "status": "healthy",
            "disks": {
                "local": {
                    "status": "healthy",
                    "duration_ms": 0.45
                },
                "public": {
                    "status": "healthy",
                    "duration_ms": 0.38
                }
            },
            "storage_used_percent": 45.67,
            "free_space_gb": 125.34,
            "last_check": "2026-04-12T10:30:00.000000Z"
        },
        "cache": {
            "status": "healthy",
            "driver": "redis",
            "duration_ms": 1.12,
            "last_check": "2026-04-12T10:30:00.000000Z"
        },
        "logging": {
            "status": "healthy",
            "log_path": "/var/www/storage/logs",
            "writable": true,
            "size_mb": 234.56,
            "last_check": "2026-04-12T10:30:00.000000Z"
        },
        "external_services": {
            "status": "healthy",
            "services": {
                "xendit": {
                    "status": "healthy",
                    "duration_ms": 145.67,
                    "status_code": 200
                },
                "firebase_fcm": {
                    "status": "healthy",
                    "message": "Credentials configured"
                },
                "cdn": {
                    "status": "healthy",
                    "duration_ms": 23.45
                }
            },
            "last_check": "2026-04-12T10:30:00.000000Z"
        }
    }
}
```

**Response - Degraded (200 OK):**
```json
{
    "status": "degraded",
    "timestamp": "2026-04-12T10:30:00.000000Z",
    "version": "1.0.0",
    "environment": "production",
    "uptime_seconds": 86400,
    "checks": {
        "database": {
            "status": "healthy",
            ...
        },
        "redis": {
            "status": "degraded",
            "message": "Redis connection slow",
            "duration_ms": 250.45
        },
        ...
    }
}
```

**Response - Unhealthy (503 Service Unavailable):**
```json
{
    "status": "unhealthy",
    "timestamp": "2026-04-12T10:30:00.000000Z",
    "version": "1.0.0",
    "environment": "production",
    "uptime_seconds": 86400,
    "checks": {
        "database": {
            "status": "unhealthy",
            "error": "Connection refused",
            "last_check": "2026-04-12T10:30:00.000000Z"
        },
        ...
    }
}
```

### 3.6 Kubernetes Configuration

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: lesgo-api
spec:
  containers:
  - name: api
    image: lesgo/api:latest
    ports:
    - containerPort: 8000
    livenessProbe:
      httpGet:
        path: /api/v1/health/liveness
        port: 8000
      initialDelaySeconds: 30
      periodSeconds: 10
      timeoutSeconds: 5
      failureThreshold: 3
    readinessProbe:
      httpGet:
        path: /api/v1/health/readiness
        port: 8000
      initialDelaySeconds: 10
      periodSeconds: 5
      timeoutSeconds: 3
      failureThreshold: 3
```

### 3.7 Nginx Health Checks

```nginx
upstream lesgo_api {
    server api1:8000 max_fails=3 fail_timeout=30s;
    server api2:8000 max_fails=3 fail_timeout=30s;
    
    # Health check endpoint
    health_check uri=/api/v1/health interval=10s fails=3 passes=2;
}

server {
    listen 80;
    
    location /api/v1/ping {
        proxy_pass http://lesgo_api;
        access_log off;  # Don't log health checks
    }
    
    location /api/v1/health {
        proxy_pass http://lesgo_api;
        access_log off;
    }
}
```

---

## 4. Business Metrics Dashboard

### 4.1 Metrics Collection Service

**Service File:** `app/Services/BusinessMetricsService.php`

### 4.2 Available Metrics

| Category | Metrics | Cache Duration |
|----------|---------|----------------|
| Overview | Users, orders, revenue, active drivers | 5 minutes |
| Orders | Status breakdown, by service, hourly distribution, completion rate | 5 minutes |
| Revenue | Daily revenue, by payment method, growth rate | 5 minutes |
| Users | User growth, by role, active users, retention | 5 minutes |
| Drivers | By status, avg rating, top drivers, utilization | 5 minutes |
| Satisfaction | Avg rating, distribution, satisfaction rate | 5 minutes |
| Support | Tickets by status, response time, resolution time | 5 minutes |
| System Performance | APM metrics, queue health, memory usage | 5 minutes |

### 4.3 Usage Examples

**Get Full Dashboard:**
```php
use App\Services\BusinessMetricsService;

$metricsService = app(BusinessMetricsService::class);
$dashboard = $metricsService->getDashboard();

// Returns all metrics categories
```

**Get Specific Metrics:**
```php
// Overview metrics
$overview = $metricsService->getOverviewMetrics();
// Returns: total_users, active_users_today, total_orders, orders_today, order_growth_percent, etc.

// Order metrics
$orders = $metricsService->getOrderMetrics();
// Returns: orders_by_status, orders_by_service, hourly_distribution, avg_order_value, completion_rate, etc.

// Revenue metrics
$revenue = $metricsService->getRevenueMetrics();
// Returns: daily_revenue, total_revenue_last_30_days, avg_daily_revenue, revenue_growth_percent, etc.

// User metrics
$users = $metricsService->getUserMetrics();
// Returns: total_users, users_by_role, active_users_7d, retention_rate_percent, etc.

// Driver metrics
$drivers = $metricsService->getDriverMetrics();
// Returns: drivers_by_status, average_rating, top_drivers, utilization_rate_percent, etc.

// Satisfaction metrics
$satisfaction = $metricsService->getSatisfactionMetrics();
// Returns: average_rating, total_reviews, rating_distribution, satisfaction_rate_percent, etc.

// Support metrics
$support = $metricsService->getSupportMetrics();
// Returns: tickets_by_status, open_tickets, overdue_tickets, avg_response_time_hours, etc.
```

### 4.4 Example Dashboard Response

```json
{
    "overview": {
        "total_users": 15234,
        "active_users_today": 3456,
        "total_orders": 45678,
        "orders_today": 234,
        "order_growth_percent": 12.34,
        "total_revenue": 3456789.50,
        "revenue_today": 12345.67,
        "revenue_growth_percent": 8.90,
        "active_drivers": 156,
        "timestamp": "2026-04-12T10:30:00.000000Z"
    },
    "orders": {
        "orders_by_status": {
            "pending": 45,
            "accepted": 23,
            "picked_up": 12,
            "completed": 45000,
            "cancelled": 567
        },
        "orders_by_service": {
            "LeSGo Delivery": 25000,
            "LesBuy": 12000,
            "LesEat": 8000
        },
        "hourly_distribution": {
            "8": 15,
            "9": 25,
            "10": 35,
            "11": 45,
            "12": 50,
            "18": 40,
            "19": 30
        },
        "average_order_value": 85.50,
        "completion_rate_percent": 95.67,
        "average_delivery_time_minutes": 32.45
    },
    "revenue": {
        "daily_revenue": [
            {
                "date": "2026-04-11",
                "revenue": 12345.67,
                "orders": 234
            }
        ],
        "total_revenue_last_30_days": 345678.90,
        "average_daily_revenue": 11522.63,
        "revenue_by_payment_method": {
            "cash": 150000.00,
            "gcash": 100000.00,
            "wallet": 95678.90
        },
        "revenue_growth_percent": 8.90
    },
    "users": {
        "total_users": 15234,
        "users_by_role": {
            "customer": 14000,
            "driver": 1000,
            "partner_admin": 200,
            "admin": 34
        },
        "active_users_7d": 8000,
        "active_users_30d": 12000,
        "retention_rate_percent": 78.76,
        "new_users_today": 45
    },
    "drivers": {
        "drivers_by_status": {
            "active": 156,
            "inactive": 50,
            "suspended": 10
        },
        "average_rating": 4.65,
        "top_drivers": [
            {
                "id": 1,
                "name": "Juan Driver",
                "rating": 4.95,
                "total_trips": 1234,
                "completion_rate": 98.50
            }
        ],
        "active_drivers_today": 89,
        "utilization_rate_percent": 57.05
    },
    "customer_satisfaction": {
        "average_rating": 4.50,
        "total_reviews": 5678,
        "rating_distribution": {
            "5": 3500,
            "4": 1500,
            "3": 500,
            "2": 100,
            "1": 78
        },
        "satisfaction_rate_percent": 88.15
    },
    "support": {
        "tickets_by_status": {
            "open": 15,
            "in_progress": 8,
            "resolved": 450,
            "closed": 500
        },
        "open_tickets": 23,
        "overdue_tickets": 3,
        "average_response_time_hours": 2.34,
        "average_resolution_time_hours": 12.45,
        "average_satisfaction_rating": 4.20
    },
    "system_performance": {
        "apm": {
            "request_count_last_minute": 145,
            "average_response_time_ms": 234.56,
            "error_rate_percent": 2.3
        },
        "queue_health": {
            "overall_status": "healthy",
            "failed_jobs_last_hour": 2
        },
        "memory_usage_mb": 45.67,
        "peak_memory_mb": 78.90
    }
}
```

### 4.5 Clear Cached Metrics

```php
$metricsService->clearCachedMetrics();
```

---

## 5. Integration with Monitoring Tools

### 5.1 Prometheus + Grafana

**Install Prometheus PHP client:**
```bash
composer require promphp/prometheus_client_php
```

**Create metrics endpoint:**
```php
// routes/api.php
Route::get('/metrics', function () {
    $storage = new Prometheus\Storage\APC();
    $registry = new Prometheus\CollectorRegistry($storage);
    
    $renderer = new Prometheus\RenderTextFormat();
    return response($renderer->render($registry->getMetricFamilySamples()))
        ->header('Content-Type', Prometheus\RenderTextFormat::MIME_TYPE);
});
```

**Grafana Dashboard JSON:**
Import the provided Grafana dashboard from `monitoring/grafana-dashboard.json`.

### 5.2 Datadog Integration

**Install Datadog tracer:**
```bash
curl -sS https://install.datadoghq.com/scripts/install_script_agent7.sh | bash
```

**Configure in `.env`:**
```env
DD_TRACE_ENABLED=true
DD_SERVICE=lesgo-api
DD_ENV=production
DD_VERSION=1.0.0
DD_LOGS_INJECTION=true
```

### 5.3 New Relic Integration

**Install New Relic agent:**
```bash
pecl install newrelic
```

**Configure `newrelic.ini`:**
```ini
extension=newrelic.so
newrelic.appname="LeSGo API"
newrelic.license="your-license-key"
newrelic.loglevel=info
```

### 5.4 ELK Stack (Elasticsearch, Logstash, Kibana)

**Filebeat configuration:**
```yaml
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /var/www/storage/logs/structured/*.log
  json.keys_under_root: true
  json.add_error_key: true
  json.message_key: message

output.elasticsearch:
  hosts: ["elasticsearch:9200"]
  indices:
    - index: "lesgo-api-%{[agent.version]}-%{+yyyy.MM.dd}"
```

**Kibana dashboard:** Import from `monitoring/kibana-dashboard.ndjson`.

---

## 6. Alerting Configuration

### 6.1 Slack Alerts

Add to `.env`:
```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

**Alert on critical errors:**
```php
// In App\Exceptions\Handler
public function report(\Throwable $exception)
{
    if ($this->shouldReport($exception)) {
        Log::channel('slack')->critical('Critical error in LeSGo API', [
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => request()->fullUrl(),
            'user_id' => request()->user()?->id,
        ]);
    }

    parent::report($exception);
}
```

### 6.2 PagerDuty Integration

```php
// Send PagerDuty alert
Http::post('https://events.pagerduty.com/v2/enqueue', [
    'routing_key' => config('services.pagerduty.routing_key'),
    'event_action' => 'trigger',
    'payload' => [
        'summary' => 'Database connection failed',
        'severity' => 'critical',
        'source' => 'lesgo-api',
        'component' => 'database',
    ],
]);
```

---

## 7. Monitoring Best Practices

1. **Always include correlation IDs** in logs and headers
2. **Monitor all external dependencies** (database, Redis, APIs)
3. **Set up alerts** for critical metrics (error rate, response time)
4. **Use structured logging** (JSON format) for easier parsing
5. **Cache metrics** to avoid performance impact
6. **Regular health checks** (every 10-30 seconds)
7. **Track business metrics** alongside technical metrics
8. **Set retention policies** for logs (30-365 days)
9. **Monitor queue health** (failed jobs, queue sizes)
10. **Review dashboards daily** for anomalies

---

## Summary

✅ **APM Integration**: New Relic, Datadog, Sentry, Elastic, Custom  
✅ **Structured Logging**: JSON logs with correlation IDs, request/response tracking  
✅ **Health Checks**: Ping, liveness, readiness, comprehensive health endpoint  
✅ **Business Metrics**: Orders, revenue, users, drivers, satisfaction, support  
✅ **Monitoring Integration**: Prometheus, Grafana, ELK, Slack alerts  

All monitoring and observability features are **production-ready** and fully integrated!
