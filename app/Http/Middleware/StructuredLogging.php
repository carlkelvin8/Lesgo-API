<?php

namespace App\Http\Middleware;

use App\Services\ApmService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Structured Logging with Correlation IDs Middleware
 * 
 * Adds unique correlation ID to each request for distributed tracing.
 * Logs all requests/responses in structured JSON format.
 * Integrates with APM service for performance monitoring.
 */
class StructuredLogging
{
    public function __construct(
        protected ApmService $apmService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or extract correlation ID
        $correlationId = $request->header('X-Correlation-ID') 
            ?? $request->header('X-Request-ID') 
            ?? (string) Str::uuid();

        // Add to request attributes
        $request->attributes->set('correlation_id', $correlationId);

        // Start APM monitoring
        $this->apmService->startRequest($request);

        // Log request
        $this->logRequest($request, $correlationId);

        // Process request
        $startTime = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $startTime) * 1000;

        // Add correlation ID to response headers
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Request-Id', $correlationId);
        $response->headers->set('X-Response-Time', round($duration, 2) . 'ms');

        return $response;
    }

    /**
     * Log after response is sent (via terminate)
     */
    public function terminate(Request $request, Response $response): void
    {
        $correlationId = $request->attributes->get('correlation_id');
        $duration = (microtime(true) - $this->getStartTime()) * 1000;

        // End APM monitoring
        $metrics = $this->apmService->endRequest($response->getStatusCode());

        // Log response
        $this->logResponse($request, $response, $correlationId, $duration, $metrics);
    }

    /**
     * Log incoming request
     */
    protected function logRequest(Request $request, string $correlationId): void
    {
        $logData = [
            'correlation_id' => $correlationId,
            'event' => 'request_started',
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'user_id' => $request->user()?->id,
            'query_params' => $this->sanitizeQueryParams($request->query()),
        ];

        Log::channel('structured')->info('Request started', $logData);
    }

    /**
     * Log response
     */
    protected function logResponse(Request $request, Response $response, string $correlationId, float $duration, array $metrics): void
    {
        $logData = [
            'correlation_id' => $correlationId,
            'event' => 'request_completed',
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'memory_used_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            'user_id' => $request->user()?->id,
            'apm_metrics' => $metrics,
        ];

        // Log level based on status code
        if ($response->getStatusCode() >= 500) {
            Log::channel('structured')->error('Request completed with error', $logData);
        } elseif ($response->getStatusCode() >= 400) {
            Log::channel('structured')->warning('Request completed with client error', $logData);
        } else {
            Log::channel('structured')->info('Request completed', $logData);
        }
    }

    /**
     * Sanitize query parameters (remove sensitive data)
     */
    protected function sanitizeQueryParams(array $params): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'credit_card', 'ssn'];

        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $params[$key] = '***REDACTED***';
            }
        }

        return $params;
    }

    /**
     * Get request start time
     */
    protected function getStartTime(): float
    {
        return defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
    }
}
