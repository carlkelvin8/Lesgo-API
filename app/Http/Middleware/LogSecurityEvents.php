<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSecurityEvents
{
    /**
     * Log security-relevant events for audit trail.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->status() === 401 || $response->status() === 403) {
            if ($this->shouldAuditUnauthorized($request)) {
                Log::warning('Security: Unauthorized access attempt', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id,
                    'status' => $response->status(),
                ]);
            }
        }

        // Log suspicious activity
        if ($this->isSuspicious($request)) {
            Log::warning('Security: Suspicious activity detected', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->except(['password', 'password_confirmation']),
            ]);
        }

        return $response;
    }

    /**
     * Skip routine/expected unauthenticated responses (reachability probes, public routes).
     */
    private function shouldAuditUnauthorized(Request $request): bool
    {
        $path = trim($request->path(), '/');

        $ignoredPrefixes = [
            'api/v1/auth/login',
            'api/v1/auth/register',
            'api/v1/auth/refresh',
            'api/v1/auth/google',
            'api/v1/auth/otp',
            'api/v1/auth/forgot-password',
            'api/v1/config/mobile',
            'api/v1/app/rider-version',
            'api/v1/app/customer-version',
            'api/v1/faq',
        ];

        foreach ($ignoredPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        // API root reachability probes (no auth header).
        if (in_array($path, ['api', ''], true) && ! $request->bearerToken()) {
            return false;
        }

        if (! $request->bearerToken()) {
            Log::debug('Auth: unauthenticated API request', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Detect suspicious patterns in requests.
     */
    private function isSuspicious(Request $request): bool
    {
        $payload = json_encode($request->all());
        
        // SQL injection patterns
        $sqlPatterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(--|\#|\/\*|\*\/)/i',
        ];

        // XSS patterns
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
        ];

        // Path traversal
        $pathPatterns = [
            '/\.\.[\/\\\\]/i',
        ];

        $allPatterns = array_merge($sqlPatterns, $xssPatterns, $pathPatterns);

        foreach ($allPatterns as $pattern) {
            if (preg_match($pattern, $payload)) {
                return true;
            }
        }

        return false;
    }
}
