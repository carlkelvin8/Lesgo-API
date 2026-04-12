<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

/**
 * Application Performance Monitoring (APM) Service
 * 
 * Integrates with popular APM tools (New Relic, Datadog, Sentry, Elastic APM)
 * and provides custom metrics tracking for application performance.
 */
class ApmService
{
    /**
     * APM Provider (newrelic, datadog, sentry, elastic, custom)
     */
    private string $provider;

    /**
     * Performance metrics cache
     */
    private array $metrics = [];

    /**
     * Request start time
     */
    private float $requestStartTime;

    public function __construct()
    {
        $this->provider = config('apm.provider', 'custom');
        $this->requestStartTime = microtime(true);
    }

    /**
     * Start monitoring request
     */
    public function startRequest(Request $request): void
    {
        $this->metrics = [
            'request_id' => $request->header('X-Request-ID') ?? uniqid('req_'),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'started_at' => microtime(true),
            'memory_start' => memory_get_usage(),
            'peak_memory_start' => memory_get_peak_usage(),
        ];
    }

    /**
     * End request and record metrics
     */
    public function endRequest(int $statusCode, ?\Throwable $exception = null): array
    {
        $duration = (microtime(true) - $this->metrics['started_at']) * 1000; // ms
        $memoryUsed = memory_get_usage() - $this->metrics['memory_start'];
        $peakMemory = memory_get_peak_usage();

        $metrics = array_merge($this->metrics, [
            'duration_ms' => round($duration, 2),
            'memory_used_bytes' => $memoryUsed,
            'peak_memory_bytes' => $peakMemory,
            'status_code' => $statusCode,
            'has_error' => $exception !== null,
            'completed_at' => microtime(true),
        ]);

        if ($exception) {
            $metrics['error'] = [
                'message' => $exception->getMessage(),
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        // Record metrics to APM provider
        $this->recordMetrics($metrics);

        // Store in cache for dashboard
        $this->storeMetrics($metrics);

        return $metrics;
    }

    /**
     * Track custom metric
     */
    public function trackMetric(string $name, float $value, array $tags = []): void
    {
        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true),
        ];

        $this->recordCustomMetric($metric);
        $this->storeCustomMetric($metric);
    }

    /**
     * Track database query performance
     */
    public function trackQuery(string $query, float $duration, array $bindings = [], bool $success = true): void
    {
        $this->trackMetric('db.query', $duration, [
            'query' => substr($query, 0, 100),
            'bindings_count' => count($bindings),
            'success' => $success,
        ]);
    }

    /**
     * Track cache performance
     */
    public function trackCache(string $operation, string $key, float $duration = 0, bool $hit = false): void
    {
        $this->trackMetric('cache.' . $operation, $duration, [
            'key_prefix' => substr($key, 0, 50),
            'hit' => $hit,
        ]);
    }

    /**
     * Track queue job performance
     */
    public function trackJob(string $jobName, float $duration, bool $success = true, string $queue = 'default'): void
    {
        $this->trackMetric('queue.job', $duration, [
            'job' => $jobName,
            'success' => $success,
            'queue' => $queue,
        ]);
    }

    /**
     * Track external API call
     */
    public function trackExternalApi(string $service, string $endpoint, float $duration, int $statusCode, bool $success = true): void
    {
        $this->trackMetric('external.api', $duration, [
            'service' => $service,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'success' => $success,
        ]);
    }

    /**
     * Record exception
     */
    public function recordException(\Throwable $exception, array $context = []): void
    {
        match ($this->provider) {
            'sentry' => $this->recordToSentry($exception, $context),
            'newrelic' => $this->recordToNewRelic($exception, $context),
            'datadog' => $this->recordToDatadog($exception, $context),
            'elastic' => $this->recordToElastic($exception, $context),
            default => $this->recordExceptionLocally($exception, $context),
        };
    }

    /**
     * Get performance summary for request
     */
    public function getPerformanceSummary(): array
    {
        return [
            'apm_provider' => $this->provider,
            'request_count_last_minute' => Cache::get('apm:requests:last_minute', 0),
            'average_response_time_ms' => Cache::get('apm:avg_response_time', 0),
            'error_rate_percent' => Cache::get('apm:error_rate', 0),
            'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
        ];
    }

    /**
     * Get APM health status
     */
    public function getHealthStatus(): array
    {
        return [
            'provider' => $this->provider,
            'connected' => $this->isProviderConnected(),
            'metrics_recorded_today' => Cache::get('apm:metrics:today', 0),
            'errors_recorded_today' => Cache::get('apm:errors:today', 0),
        ];
    }

    // ── Private Methods ─────────────────────────────────────────────────────

    /**
     * Record metrics to APM provider
     */
    private function recordMetrics(array $metrics): void
    {
        match ($this->provider) {
            'newrelic' => $this->recordToNewRelicRequest($metrics),
            'datadog' => $this->recordToDatadogRequest($metrics),
            'elastic' => $this->recordToElasticRequest($metrics),
            default => $this->recordMetricsLocally($metrics),
        };
    }

    /**
     * Record custom metric
     */
    private function recordCustomMetric(array $metric): void
    {
        match ($this->provider) {
            'newrelic' => $this->recordCustomMetricNewRelic($metric),
            'datadog' => $this->recordCustomMetricDatadog($metric),
            'elastic' => $this->recordCustomMetricElastic($metric),
            default => $this->recordCustomMetricLocally($metric),
        };
    }

    /**
     * Store metrics for dashboard
     */
    private function storeMetrics(array $metrics): void
    {
        // Increment request count
        Cache::increment('apm:requests:last_minute');
        Cache::put('apm:requests:last_minute', Cache::get('apm:requests:last_minute', 0), now()->addMinute());

        // Update average response time
        $currentAvg = Cache::get('apm:avg_response_time', 0);
        $newAvg = $currentAvg > 0 
            ? ($currentAvg + $metrics['duration_ms']) / 2 
            : $metrics['duration_ms'];
        Cache::put('apm:avg_response_time', round($newAvg, 2), now()->addHour());

        // Update error rate
        $totalRequests = Cache::get('apm:requests:last_minute', 0);
        $errorCount = $metrics['has_error'] ? 1 : 0;
        $errorRate = $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0;
        Cache::put('apm:error_rate', round($errorRate, 2), now()->addMinute());

        // Store today's metrics
        $today = now()->format('Y-m-d');
        Cache::increment("apm:metrics:{$today}");
        if ($metrics['has_error']) {
            Cache::increment("apm:errors:{$today}");
        }
    }

    /**
     * Store custom metric
     */
    private function storeCustomMetric(array $metric): void
    {
        $key = "apm:custom_metric:{$metric['name']}";
        $metrics = Cache::get($key, []);
        $metrics[] = $metric;
        
        // Keep only last 100 metrics
        if (count($metrics) > 100) {
            $metrics = array_slice($metrics, -100);
        }
        
        Cache::put($key, $metrics, now()->addHour());
    }

    // ── Provider-Specific Methods ───────────────────────────────────────────

    private function isProviderConnected(): bool
    {
        return match ($this->provider) {
            'newrelic' => extension_loaded('newrelic'),
            'sentry' => class_exists('\\Sentry\\State\\Hub'),
            'datadog' => function_exists('datadog_trace_measure'),
            'elastic' => class_exists('\\Elastic\\Apm\\ElasticApm'),
            default => true,
        };
    }

    private function recordToSentry(\Throwable $exception, array $context): void
    {
        if (class_exists('\\Sentry\\Sentry')) {
            \Sentry\Sentry::captureException($exception);
        }
    }

    private function recordToNewRelic(\Throwable $exception, array $context): void
    {
        if (extension_loaded('newrelic')) {
            newrelic_notice_error($exception);
        }
    }

    private function recordToDatadog(\Throwable $exception, array $context): void
    {
        // Datadog PHP tracer automatically captures exceptions
        Log::error('Datadog APM: Exception captured', [
            'exception' => $exception->getMessage(),
            'context' => $context,
        ]);
    }

    private function recordToElastic(\Throwable $exception, array $context): void
    {
        if (class_exists('\\Elastic\\Apm\\ElasticApm')) {
            $transaction = \Elastic\Apm\ElasticApm::getCurrentTransaction();
            if ($transaction) {
                $transaction->captureThrowable($exception);
            }
        }
    }

    private function recordExceptionLocally(\Throwable $exception, array $context): void
    {
        Log::error('APM: Exception captured', [
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context,
        ]);
    }

    private function recordToNewRelicRequest(array $metrics): void
    {
        if (extension_loaded('newrelic')) {
            newrelic_add_custom_parameter('request_id', $metrics['request_id']);
            newrelic_add_custom_parameter('duration_ms', $metrics['duration_ms']);
            newrelic_add_custom_parameter('memory_used', $metrics['memory_used_bytes']);
            
            if ($metrics['has_error']) {
                newrelic_notice_error($metrics['error']['message']);
            }
        }
    }

    private function recordToDatadogRequest(array $metrics): void
    {
        // Datadog automatically tracks request metrics when tracer is enabled
        Log::debug('Datadog APM: Request metrics', $metrics);
    }

    private function recordToElasticRequest(array $metrics): void
    {
        if (class_exists('\\Elastic\\Apm\\ElasticApm')) {
            $transaction = \Elastic\Apm\ElasticApm::getCurrentTransaction();
            if ($transaction) {
                $transaction->setLabel('request_id', $metrics['request_id']);
                $transaction->setLabel('status_code', $metrics['status_code']);
            }
        }
    }

    private function recordMetricsLocally(array $metrics): void
    {
        Log::info('APM: Request completed', $metrics);
    }

    private function recordCustomMetricNewRelic(array $metric): void
    {
        if (extension_loaded('newrelic')) {
            newrelic_custom_metric($metric['name'], $metric['value']);
        }
    }

    private function recordCustomMetricDatadog(array $metric): void
    {
        // Datadog statsd client
        Log::debug('Datadog APM: Custom metric', $metric);
    }

    private function recordCustomMetricElastic(array $metric): void
    {
        // Elastic APM custom metric
        Log::debug('Elastic APM: Custom metric', $metric);
    }

    private function recordCustomMetricLocally(array $metric): void
    {
        Log::debug('APM: Custom metric', $metric);
    }
}
