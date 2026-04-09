<?php

namespace App\Http\Middleware;

use App\Models\RateLimitRule;
use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AdvancedRateLimiting
{
    private SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request with advanced rate limiting
     */
    public function handle(Request $request, Closure $next): Response
    {
        $endpoint = $request->path();
        $method = $request->method();
        $ip = $request->ip();
        $userId = $request->user()?->id;

        // Get applicable rate limit rules
        $rules = RateLimitRule::active()
            ->byPriority()
            ->get()
            ->filter(fn($rule) => $rule->matches($endpoint, $method));

        foreach ($rules as $rule) {
            $identifier = $this->getIdentifier($rule, $ip, $userId);
            $key = $rule->getRateLimitKey($identifier);

            if (RateLimiter::tooManyAttempts($key, $rule->max_attempts)) {
                $seconds = RateLimiter::availableIn($key);

                // Log rate limit violation
                $this->securityService->logSecurityEvent([
                    'user_id' => $userId,
                    'event_type' => 'rate_limit_exceeded',
                    'severity' => 'warning',
                    'source' => 'rate_limiter',
                    'description' => "Rate limit exceeded for rule: {$rule->name}",
                    'event_data' => [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'max_attempts' => $rule->max_attempts,
                        'window_minutes' => $rule->window_minutes,
                        'retry_after' => $seconds,
                    ],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'request_id' => $request->header('X-Request-ID'),
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'retry_after' => $seconds,
                        'rule' => $rule->name,
                    ],
                ], 429, [
                    'Retry-After' => $seconds,
                    'X-RateLimit-Limit' => $rule->max_attempts,
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => now()->addSeconds($seconds)->timestamp,
                ]);
            }

            // Hit the rate limiter
            RateLimiter::hit($key, $rule->window_minutes * 60);
        }

        $response = $next($request);

        // Add rate limit headers for the most restrictive rule
        if ($rules->isNotEmpty()) {
            $mostRestrictive = $rules->first();
            $identifier = $this->getIdentifier($mostRestrictive, $ip, $userId);
            $key = $mostRestrictive->getRateLimitKey($identifier);
            
            $remaining = max(0, $mostRestrictive->max_attempts - RateLimiter::attempts($key));
            $resetTime = RateLimiter::availableIn($key);

            $response->headers->set('X-RateLimit-Limit', $mostRestrictive->max_attempts);
            $response->headers->set('X-RateLimit-Remaining', $remaining);
            $response->headers->set('X-RateLimit-Reset', now()->addSeconds($resetTime)->timestamp);
        }

        return $response;
    }

    /**
     * Get identifier based on rule scope
     */
    private function getIdentifier(RateLimitRule $rule, string $ip, ?int $userId): string
    {
        return match($rule->scope) {
            'user' => $userId ? "user:{$userId}" : "ip:{$ip}",
            'ip' => "ip:{$ip}",
            'global' => 'global',
            default => "ip:{$ip}"
        };
    }
}