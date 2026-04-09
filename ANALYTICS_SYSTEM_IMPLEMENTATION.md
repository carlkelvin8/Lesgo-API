# Advanced Analytics & Business Intelligence System

## Overview
Comprehensive analytics and business intelligence system providing deep insights into revenue, driver performance, customer behavior, service demand patterns, geofence effectiveness, and predictive analytics for data-driven decision making.

## Features Implemented

### 📊 **Revenue Analytics & Forecasting**
- Real-time revenue tracking and analysis
- Revenue breakdown by type, source, service, and partner
- Growth rate calculations and trend analysis
- Revenue forecasting using predictive models
- Margin analysis and profitability metrics

### 🚗 **Driver Performance Metrics**
- Comprehensive driver performance scoring
- Completion rates, acceptance rates, and efficiency metrics
- Revenue per hour and orders per hour analysis
- Customer satisfaction and complaint tracking
- Top performer identification and rankings

### 👥 **Customer Behavior Analysis**
- Customer segmentation (VIP, High Value, Regular, Occasional, Inactive)
- Customer Lifetime Value (CLV) calculations
- Churn probability prediction and risk analysis
- Engagement level tracking and retention analysis
- Preferred services and usage pattern analysis

### 🛍️ **Service Demand Patterns**
- Demand forecasting by service type
- Peak hour identification and seasonal patterns
- Supply-demand ratio analysis
- Completion rates and wait time analytics
- Service performance optimization insights

### 🗺️ **Geofence Effectiveness Analytics**
- Conversion rate tracking for geofenced areas
- Revenue generation per geofence
- Notification click-through rates
- ROI analysis for location-based marketing
- Geofence performance scoring and optimization

### 🔮 **Predictive Analytics**
- Demand forecasting models
- Revenue prediction algorithms
- Customer churn prediction
- Driver performance trend analysis
- Seasonal adjustment recommendations

## Database Schema

### Analytics Events Table
```sql
analytics_events:
- id (primary key)
- user_id (foreign key, nullable)
- event_type (string) - order_created, driver_online, etc.
- event_category (string) - order, driver, customer, payment
- event_action (string) - create, update, delete, view
- event_label (string, nullable)
- event_value (decimal, nullable) - monetary value
- properties (json) - additional event data
- session_id, device_type, platform, app_version
- ip_address, user_agent, latitude, longitude
- event_time (timestamp)
- created_at, updated_at
```

### Daily Metrics Table
```sql
daily_metrics:
- id (primary key)
- date (date)
- metric_type (string) - revenue, orders, drivers, customers
- metric_category (string, nullable) - service_type, region
- metric_key (string) - total_revenue, completed_orders
- metric_value (decimal)
- metadata (json)
- created_at, updated_at
```

### Driver Performance Metrics Table
```sql
driver_performance_metrics:
- id (primary key)
- driver_id (foreign key)
- date (date)
- total_orders, completed_orders, cancelled_orders
- total_revenue, total_distance_km, online_minutes
- average_rating, total_ratings
- acceptance_rate, completion_rate
- average_delivery_time, customer_complaints
- performance_data (json)
- created_at, updated_at
```

### Customer Behavior Metrics Table
```sql
customer_behavior_metrics:
- id (primary key)
- customer_id (foreign key)
- date (date)
- total_orders, completed_orders, cancelled_orders
- total_spent, average_order_value
- app_sessions, session_duration_minutes
- preferred_services, preferred_times, preferred_locations (json)
- customer_lifetime_value, referrals_made
- churn_probability
- behavior_data (json)
- created_at, updated_at
```

### Service Demand Metrics Table
```sql
service_demand_metrics:
- id (primary key)
- service_id (foreign key)
- date (date)
- hour_of_day (0-23), day_of_week (1-7)
- total_requests, completed_requests, cancelled_requests
- total_revenue, average_wait_time, average_completion_time
- peak_demand_score, supply_demand_ratio
- demand_data (json)
- created_at, updated_at
```

### Geofence Analytics Table
```sql
geofence_analytics:
- id (primary key)
- geofence_id (foreign key)
- date (date)
- total_entries, total_exits, total_dwells
- unique_users, average_dwell_time
- orders_triggered, conversion_rate
- revenue_generated, notifications_sent
- notification_clicks, notification_ctr
- effectiveness_data (json)
- created_at, updated_at
```

### Revenue Analytics Table
```sql
revenue_analytics:
- id (primary key)
- date (date)
- revenue_type (string) - gross, net, commission, driver_earnings
- revenue_source (string) - orders, subscriptions, fees
- service_id, partner_id (foreign keys, nullable)
- amount, currency, transaction_count
- average_transaction_value
- breakdown (json)
- created_at, updated_at
```

## API Endpoints

### Analytics Dashboard
- `GET /api/v1/analytics/dashboard` - Comprehensive dashboard data
- `GET /api/v1/analytics/revenue` - Revenue analytics and forecasting
- `GET /api/v1/analytics/drivers/performance` - Driver performance metrics
- `GET /api/v1/analytics/customers/behavior` - Customer behavior analysis
- `GET /api/v1/analytics/services/demand` - Service demand patterns
- `GET /api/v1/analytics/geofences/effectiveness` - Geofence analytics
- `GET /api/v1/analytics/predictions` - Predictive insights
- `GET /api/v1/analytics/events` - Analytics events history
- `POST /api/v1/analytics/events/track` - Track custom events
- `POST /api/v1/analytics/export` - Export analytics data

## Core Services

### AnalyticsService
Main service for analytics operations:

```php
// Track events
$analyticsService->trackEvent(
    'order_completed',
    'order',
    'complete',
    $user,
    'delivery_order',
    150.00,
    ['service_id' => 1, 'driver_id' => 123]
);

// Get dashboard data
$dashboard = $analyticsService->getDashboardData($startDate, $endDate);

// Calculate daily metrics
$analyticsService->calculateDailyMetrics($date);

// Get predictive insights
$predictions = $analyticsService->getPredictiveInsights($startDate, $endDate);
```

## Analytics Models

### AnalyticsEvent
```php
// Track events
AnalyticsEvent::track(
    'user_login',
    'auth',
    'login',
    $user,
    null,
    null,
    ['login_method' => 'email'],
    ['device_type' => 'mobile']
);

// Query events
$events = AnalyticsEvent::byCategory('order')
    ->thisMonth()
    ->withValue()
    ->get();
```

### DailyMetric
```php
// Record metrics
DailyMetric::record(today(), 'revenue', 'total_revenue', 15000.00);

// Increment counters
DailyMetric::increment(today(), 'orders', 'completed_orders', 1);

// Get growth rate
$metric = DailyMetric::forDate(today())->first();
$growthRate = $metric->getGrowthRate();
```

### DriverPerformanceMetric
```php
// Calculate performance score
$metric = DriverPerformanceMetric::forDriver($driverId)->first();
$score = $metric->calculatePerformanceScore(); // 0-100

// Check if top performer
$isTopPerformer = $metric->isTopPerformer();

// Get efficiency rating
$rating = $metric->getEfficiencyRating(); // Excellent, Good, etc.
```

### CustomerBehaviorMetric
```php
// Get customer segment
$metric = CustomerBehaviorMetric::forCustomer($customerId)->first();
$segment = $metric->getCustomerSegment(); // VIP, High Value, etc.

// Calculate retention score
$retentionScore = $metric->calculateRetentionScore();

// Get churn risk
$churnRisk = $metric->getChurnRisk(); // Very High, High, etc.
```

## Dashboard Analytics

### Revenue Dashboard
```json
{
  "total_revenue": 125000.50,
  "average_daily_revenue": 4166.68,
  "revenue_by_type": {
    "gross": 125000.50,
    "commission": 12500.05,
    "driver_earnings": 87500.35
  },
  "growth_rate": 15.5,
  "forecast": [4200, 4350, 4500, 4650]
}
```

### Driver Performance Dashboard
```json
{
  "total_drivers": 150,
  "active_drivers": 120,
  "average_performance_score": 78.5,
  "top_performers": [
    {
      "driver": "John Doe",
      "performance_score": 95.2,
      "completion_rate": 98.5,
      "average_rating": 4.9,
      "total_revenue": 2500.00
    }
  ],
  "performance_distribution": {
    "excellent": 25,
    "very_good": 45,
    "good": 35,
    "average": 20,
    "needs_improvement": 5
  }
}
```

### Customer Behavior Dashboard
```json
{
  "total_customers": 2500,
  "active_customers": 1800,
  "average_clv": 850.00,
  "churn_risk_customers": 125,
  "customer_segments": {
    "vip": 50,
    "high_value": 200,
    "regular": 800,
    "occasional": 600,
    "inactive": 850
  },
  "retention_analysis": {
    "high_retention": 1200,
    "medium_retention": 800,
    "low_retention": 500
  }
}
```

## Predictive Analytics

### Demand Forecasting
```php
// Predict demand for next 7 days
$demandForecast = $analyticsService->predictDemand(now()->addDays(7));

// Results include:
// - Predicted order volume by hour
// - Service-specific demand patterns
// - Confidence intervals
// - Seasonal adjustments
```

### Revenue Forecasting
```php
// Predict revenue for next 30 days
$revenueForecast = $analyticsService->predictRevenue(now()->addDays(30));

// Results include:
// - Daily revenue predictions
// - Growth trend analysis
// - Seasonal factors
// - Confidence scores
```

### Churn Prediction
```php
// Identify customers at risk of churning
$churnPredictions = $analyticsService->predictCustomerChurn();

// Results include:
// - Churn probability scores
// - Risk factors
// - Recommended interventions
// - Timeline predictions
```

## Automated Analytics

### Daily Calculation Job
```php
// Queue daily analytics calculation
CalculateDailyAnalyticsJob::dispatch(Carbon::yesterday());

// Or run via command
php artisan analytics:calculate --date=2026-04-09 --queue
```

### Event Tracking Middleware
Automatically tracks API events:
```php
// Automatically tracks:
// - Order creation/completion
// - User authentication
// - Payment processing
// - API usage patterns
// - Performance metrics
```

## Performance Optimizations

### Caching Strategy
- Dashboard data cached for 5 minutes
- Analytics queries optimized with indexes
- Aggregated metrics pre-calculated daily
- Redis caching for frequently accessed data

### Database Indexing
```sql
-- Key indexes for performance
CREATE INDEX idx_analytics_events_type_time ON analytics_events(event_type, event_time);
CREATE INDEX idx_analytics_events_user_time ON analytics_events(user_id, event_time);
CREATE INDEX idx_daily_metrics_date_type ON daily_metrics(date, metric_type);
CREATE INDEX idx_driver_performance_date ON driver_performance_metrics(date, completed_orders);
```

### Query Optimization
- Efficient date range queries
- Proper use of database aggregations
- Batch processing for large datasets
- Pagination for large result sets

## Security & Access Control

### Role-Based Access
- **Admin**: Full access to all analytics
- **Partner Admin**: Access to partner-specific data
- **Driver**: Access to own performance metrics
- **Customer**: Access to own usage statistics

### Data Privacy
- PII data anonymization in analytics
- GDPR compliance for data retention
- Secure API endpoints with authentication
- Audit logging for sensitive operations

## Integration Examples

### JavaScript Dashboard
```javascript
// Fetch dashboard data
const response = await fetch('/api/v1/analytics/dashboard?period=month', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});

const analytics = await response.json();
console.log('Revenue:', analytics.data.analytics.revenue.total_revenue);
```

### Flutter Integration
```dart
// Track custom event
await http.post(
  Uri.parse('$baseUrl/api/v1/analytics/events/track'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
  },
  body: jsonEncode({
    'event_type': 'order_viewed',
    'event_category': 'order',
    'event_action': 'view',
    'event_label': 'order_details',
    'properties': {'order_id': 123}
  }),
);
```

## Reporting & Visualization

### Export Capabilities
- JSON, CSV, Excel export formats
- Scheduled report generation
- Custom date range selection
- Filtered data exports

### Visualization Ready
Data formatted for popular charting libraries:
- Chart.js
- D3.js
- Highcharts
- Flutter Charts

## Monitoring & Alerts

### Performance Monitoring
- Analytics calculation performance
- Database query optimization
- Memory usage tracking
- Error rate monitoring

### Business Alerts
- Revenue threshold alerts
- Driver performance warnings
- Customer churn risk notifications
- Demand spike alerts

## Future Enhancements

### Advanced ML Models
- Deep learning for demand prediction
- Customer behavior clustering
- Anomaly detection algorithms
- Real-time recommendation engines

### Enhanced Visualizations
- Interactive dashboards
- Real-time data streaming
- Geographic heat maps
- Advanced filtering options

## Usage Examples

### Get Revenue Analytics
```bash
curl -X GET "https://your-api.com/api/v1/analytics/revenue?start_date=2026-04-01&end_date=2026-04-09" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Track Custom Event
```bash
curl -X POST "https://your-api.com/api/v1/analytics/events/track" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "feature_used",
    "event_category": "app",
    "event_action": "use",
    "event_label": "live_tracking",
    "properties": {"feature_version": "1.0"}
  }'
```

### Export Dashboard Data
```bash
curl -X POST "https://your-api.com/api/v1/analytics/export" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "export_type": "dashboard",
    "format": "json",
    "start_date": "2026-04-01",
    "end_date": "2026-04-09"
  }'
```

## Command Line Tools

### Calculate Analytics
```bash
# Calculate for yesterday
php artisan analytics:calculate

# Calculate for specific date
php artisan analytics:calculate --date=2026-04-09

# Calculate for multiple days
php artisan analytics:calculate --date=2026-04-09 --days=7

# Queue the calculations
php artisan analytics:calculate --date=2026-04-09 --days=7 --queue
```

---

**Status**: ✅ COMPLETE - Advanced Analytics & Business Intelligence system fully implemented
**Last Updated**: April 9, 2026
**Version**: 1.0.0

## Quick Start Guide

1. **Run Migrations**: `php artisan migrate`
2. **Calculate Historical Data**: `php artisan analytics:calculate --days=30 --queue`
3. **Access Dashboard**: `GET /api/v1/analytics/dashboard`
4. **Track Events**: `POST /api/v1/analytics/events/track`
5. **Schedule Daily Calculations**: Add job to cron/scheduler

The analytics system provides comprehensive business intelligence with real-time insights, predictive analytics, and automated reporting to drive data-driven decisions for your LeSGo platform!