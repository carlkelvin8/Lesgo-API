<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        // Accept an incoming X-Request-ID (e.g. from mobile client) or generate one
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();

        // Bind so anything in the app can resolve it
        app()->instance('request_id', $requestId);

        $response = $next($request);

        // Always echo it back in the response header
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
