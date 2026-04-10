<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdvancedAuditLogging
{
    private SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request with advanced audit logging
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $endTime = microtime(true);

        // Log the request
        $this->logRequest($request, $response, $endTime - $startTime);

        // Check for suspicious activity
        $suspiciousPatterns = $this->securityService->analyzeSuspiciousActivity($request);
        if (!empty($suspiciousPatterns)) {
            $this->securityService->handleSuspiciousActivity($request, $suspiciousPatterns);
        }

        return $response;
    }

    /**
     * Log the request details
     */
    private function logRequest(Request $request, Response $response, float $duration): void
    {
        $user = $request->user();
        $statusCode = $response->getStatusCode();

        // Determine event type and risk level
        $eventType = $this->getEventType($request, $statusCode);
        $riskLevel = $this->getRiskLevel($request, $statusCode);

        // Only log significant events to avoid noise
        if ($this->shouldLog($request, $statusCode)) {
            $this->securityService->logAuditEvent([
                'user_id' => $user?->id,
                'event_type' => $eventType,
                'event_category' => $this->getEventCategory($request),
                'action' => strtolower($request->method()),
                'resource_type' => $this->getResourceType($request),
                'resource_id' => $this->getResourceId($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'risk_level' => $riskLevel,
                'is_suspicious' => !empty($this->securityService->analyzeSuspiciousActivity($request)),
                'context' => [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'status_code' => $statusCode,
                    'duration_ms' => round($duration * 1000, 2),
                    'payload_size' => strlen(json_encode($request->except(['password', 'password_confirmation']))),
                ],
                'occurred_at' => now(),
            ]);
        }
    }

    /**
     * Determine if request should be logged
     */
    private function shouldLog(Request $request, int $statusCode): bool
    {
        // Always log authentication events
        if (str_contains($request->path(), 'auth') || str_contains($request->path(), 'login')) {
            return true;
        }

        // Always log errors
        if ($statusCode >= 400) {
            return true;
        }

        // Log sensitive operations
        $sensitivePatterns = [
            '/api/v1/users',
            '/api/v1/payments',
            '/api/v1/orders',
            '/api/v1/security',
            '/api/v1/admin',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($request->path(), trim($pattern, '/'))) {
                return true;
            }
        }

        // Log write operations
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }

        return false;
    }

    /**
     * Get event type based on request
     */
    private function getEventType(Request $request, int $statusCode): string
    {
        if (str_contains($request->path(), 'login')) {
            return $statusCode < 400 ? 'login_success' : 'login_failed';
        }

        if (str_contains($request->path(), 'logout')) {
            return 'logout';
        }

        if ($statusCode >= 400) {
            return 'api_error';
        }

        return match($request->method()) {
            'GET' => 'data_access',
            'POST' => 'data_creation',
            'PUT', 'PATCH' => 'data_modification',
            'DELETE' => 'data_deletion',
            default => 'api_request'
        };
    }

    /**
     * Get event category
     */
    private function getEventCategory(Request $request): string
    {
        if (str_contains($request->path(), 'auth') || str_contains($request->path(), 'login')) {
            return 'authentication';
        }

        if (str_contains($request->path(), 'admin')) {
            return 'authorization';
        }

        if (str_contains($request->path(), 'payment')) {
            return 'financial';
        }

        return 'data';
    }

    /**
     * Get resource type from request
     */
    private function getResourceType(Request $request): ?string
    {
        $path = $request->path();
        
        if (preg_match('/api\/v1\/(\w+)/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get resource ID from request
     */
    private function getResourceId(Request $request): ?string
    {
        $path = $request->path();
        
        if (preg_match('/api\/v1\/\w+\/(\d+)/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Determine risk level
     */
    private function getRiskLevel(Request $request, int $statusCode): string
    {
        // Critical for failed admin operations
        if (str_contains($request->path(), 'admin') && $statusCode >= 400) {
            return 'critical';
        }

        // High for payment operations
        if (str_contains($request->path(), 'payment')) {
            return 'high';
        }

        // High for authentication failures
        if (str_contains($request->path(), 'auth') && $statusCode >= 400) {
            return 'high';
        }

        // Medium for write operations
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return 'medium';
        }

        // Medium for errors
        if ($statusCode >= 400) {
            return 'medium';
        }

        return 'low';
    }
}