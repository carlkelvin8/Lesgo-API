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

        // Log failed authentication attempts
        if ($response->status() === 401 || $response->status() === 403) {
            Log::warning('Security: Unauthorized access attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'status' => $response->status(),
            ]);
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
