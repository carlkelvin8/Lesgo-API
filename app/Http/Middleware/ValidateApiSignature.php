<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiSignature
{
    /**
     * Validate API request signature for sensitive operations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for non-production environments if needed
        if (app()->environment('local') && !config('app.enforce_signatures')) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');

        if (!$signature || !$timestamp) {
            return response()->json([
                'success' => false,
                'message' => 'Missing security headers',
            ], 401);
        }

        // Prevent replay attacks (5 minute window)
        if (abs(time() - $timestamp) > 300) {
            return response()->json([
                'success' => false,
                'message' => 'Request expired',
            ], 401);
        }

        // Validate signature
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp . $payload, config('app.api_secret'));

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
        }

        return $next($request);
    }
}
