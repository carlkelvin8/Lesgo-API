<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TrackAnalyticsEvents
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track successful API requests
        if ($request->is('api/*') && $response->getStatusCode() < 400) {
            $this->trackApiEvent($request, $response);
        }

        return $response;
    }

    private function trackApiEvent(Request $request, $response): void
    {
        try {
            $user = Auth::user();
            $method = $request->method();
            $path = $request->path();
            $statusCode = $response->getStatusCode();

            // Determine event category and action
            [$category, $action, $label] = $this->parseRequestPath($path, $method);

            // Skip tracking for certain endpoints
            if ($this->shouldSkipTracking($path)) {
                return;
            }

            // Extract event value (for revenue-related events)
            $eventValue = $this->extractEventValue($request, $response);

            // Build event properties
            $properties = [
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
                'response_time' => $this->getResponseTime($request),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ];

            // Add request-specific data
            if ($request->has('order_id')) {
                $properties['order_id'] = $request->get('order_id');
            }

            if ($request->has('service_id')) {
                $properties['service_id'] = $request->get('service_id');
            }

            // Track the event
            $this->analyticsService->trackEvent(
                $this->generateEventType($category, $action),
                $category,
                $action,
                $user,
                $label,
                $eventValue,
                $properties,
                [
                    'session_id' => $request->session()->getId(),
                    'device_type' => $this->detectDeviceType($request),
                    'platform' => $this->detectPlatform($request),
                    'ip_address' => $request->ip(),
                ]
            );

        } catch (\Exception $e) {
            // Log error but don't break the request
            \Log::warning('Failed to track analytics event', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }
    }

    private function parseRequestPath(string $path, string $method): array
    {
        $segments = explode('/', trim($path, '/'));
        
        // Remove 'api' and version segments
        $segments = array_slice($segments, 2);

        if (empty($segments)) {
            return ['api', 'request', null];
        }

        $category = $segments[0] ?? 'api';
        
        // Map HTTP methods to actions
        $action = match ($method) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'request',
        };

        // Special cases for specific endpoints
        if (str_contains($path, '/login')) {
            return ['auth', 'login', null];
        }

        if (str_contains($path, '/register')) {
            return ['auth', 'register', null];
        }

        if (str_contains($path, '/orders') && $method === 'POST') {
            return ['order', 'create', 'order_created'];
        }

        if (str_contains($path, '/orders') && str_contains($path, '/status')) {
            return ['order', 'update_status', 'status_updated'];
        }

        if (str_contains($path, '/payments') && $method === 'POST') {
            return ['payment', 'create', 'payment_made'];
        }

        $label = count($segments) > 1 ? $segments[1] : null;

        return [$category, $action, $label];
    }

    private function shouldSkipTracking(string $path): bool
    {
        $skipPatterns = [
            'api/v1/ping',
            'api/v1/analytics',
            'api/v1/realtime/ping',
            'api/v1/realtime/connections',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function extractEventValue(Request $request, $response): ?float
    {
        // Extract monetary value from payment requests
        if (str_contains($request->path(), '/payments')) {
            return $request->get('amount');
        }

        // Extract fare from order completion
        if (str_contains($request->path(), '/orders') && $request->has('actual_fare')) {
            return $request->get('actual_fare');
        }

        // Extract value from response for completed orders
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);
            
            if (isset($data['data']['actual_fare'])) {
                return $data['data']['actual_fare'];
            }

            if (isset($data['data']['amount'])) {
                return $data['data']['amount'];
            }
        }

        return null;
    }

    private function generateEventType(string $category, string $action): string
    {
        return "{$category}_{$action}";
    }

    private function getResponseTime(Request $request): ?float
    {
        if (defined('LARAVEL_START')) {
            return round((microtime(true) - LARAVEL_START) * 1000, 2);
        }

        return null;
    }

    private function detectDeviceType(Request $request): string
    {
        $userAgent = $request->userAgent();

        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android') || str_contains($userAgent, 'iPhone')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'Tablet') || str_contains($userAgent, 'iPad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    private function detectPlatform(Request $request): string
    {
        $userAgent = $request->userAgent();

        if (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iOS')) {
            return 'ios';
        }

        if (str_contains($userAgent, 'Android')) {
            return 'android';
        }

        if (str_contains($userAgent, 'Windows')) {
            return 'windows';
        }

        if (str_contains($userAgent, 'Mac')) {
            return 'macos';
        }

        if (str_contains($userAgent, 'Linux')) {
            return 'linux';
        }

        return 'web';
    }
}