<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache Response Middleware
 * Caches GET request responses to reduce server load
 */
class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $ttl  Time to live in seconds (default: 300 = 5 minutes)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, int $ttl = 300): Response
    {
        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Don't cache if user is authenticated (personalized content)
        if ($request->user()) {
            return $next($request);
        }

        // Generate cache key
        $key = $this->getCacheKey($request);

        // Try to get from cache
        $cachedResponse = Cache::get($key);

        if ($cachedResponse !== null) {
            return $this->createResponseFromCache($cachedResponse);
        }

        // Get fresh response
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful() && $response instanceof \Illuminate\Http\JsonResponse) {
            $this->cacheResponse($key, $response, $ttl);
        }

        // Add cache headers
        $response->headers->add([
            'X-Cache-Status' => 'MISS',
            'X-Cache-TTL' => $ttl,
        ]);

        return $response;
    }

    /**
     * Generate cache key for request
     */
    protected function getCacheKey(Request $request): string
    {
        $uri = $request->getRequestUri();
        $query = $request->getQueryString();
        
        return 'response-cache:' . md5($uri . $query);
    }

    /**
     * Cache the response
     */
    protected function cacheResponse(string $key, Response $response, int $ttl): void
    {
        $data = [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
        ];

        Cache::put($key, $data, $ttl);
    }

    /**
     * Create response from cached data
     */
    protected function createResponseFromCache(array $data): Response
    {
        $response = response($data['content'], $data['status']);

        // Restore headers
        foreach ($data['headers'] as $key => $values) {
            $response->headers->set($key, $values);
        }

        // Add cache hit header
        $response->headers->add([
            'X-Cache-Status' => 'HIT',
        ]);

        return $response;
    }
}
