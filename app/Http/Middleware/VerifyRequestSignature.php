<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request Signature Verification Middleware
 * 
 * Verifies HMAC-SHA256 signatures for sensitive API operations to prevent
 * request tampering and ensure request integrity.
 * 
 * Usage: Add to routes that require signature verification
 * Example: sensitive endpoints, financial transactions, admin operations
 */
class VerifyRequestSignature
{
    /**
     * Signature max age in seconds (5 minutes)
     */
    private const MAX_AGE_SECONDS = 300;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip signature verification in local environment unless forced
        if (app()->environment('local') && !$request->boolean('force_signature_check')) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');

        // Validate required headers
        if (!$signature || !$timestamp || !$nonce) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required signature headers',
                'required_headers' => ['X-Signature', 'X-Timestamp', 'X-Nonce'],
            ], 401);
        }

        // Validate timestamp (prevent replay attacks)
        $requestTime = (int) $timestamp;
        $currentTime = time();
        
        if (abs($currentTime - $requestTime) > self::MAX_AGE_SECONDS) {
            return response()->json([
                'success' => false,
                'message' => 'Request timestamp expired',
                'max_age_seconds' => self::MAX_AGE_SECONDS,
            ], 401);
        }

        // Validate nonce (prevent replay attacks)
        if ($this->isNonceUsed($nonce)) {
            return response()->json([
                'success' => false,
                'message' => 'Nonce already used',
            ], 401);
        }

        // Verify signature
        $expectedSignature = $this->generateSignature($request, $timestamp, $nonce);
        
        if (!hash_equals($expectedSignature, $signature)) {
            // Log security event
            $this->logSecurityEvent($request, 'invalid_signature');

            return response()->json([
                'success' => false,
                'message' => 'Invalid request signature',
            ], 401);
        }

        // Mark nonce as used
        $this->markNonceAsUsed($nonce);

        return $next($request);
    }

    /**
     * Generate expected signature for the request
     */
    private function generateSignature(Request $request, string $timestamp, string $nonce): string
    {
        $secret = config('services.request_signature.secret', env('REQUEST_SIGNATURE_SECRET'));
        
        if (!$secret) {
            throw new \RuntimeException('Request signature secret not configured');
        }

        // Build signature string
        $method = strtoupper($request->method());
        $path = $request->path();
        $body = $request->getContent() ?: '';
        
        $signatureString = "{$method}\n{$path}\n{$timestamp}\n{$nonce}\n{$body}";

        return hash_hmac('sha256', $signatureString, $secret);
    }

    /**
     * Check if nonce has been used
     */
    private function isNonceUsed(string $nonce): bool
    {
        // Use cache to track nonces (expires after MAX_AGE_SECONDS)
        $cacheKey = "signature_nonce:{$nonce}";
        
        return cache()->has($cacheKey);
    }

    /**
     * Mark nonce as used
     */
    private function markNonceAsUsed(string $nonce): void
    {
        $cacheKey = "signature_nonce:{$nonce}";
        
        // Store nonce until signature expires
        cache()->put($cacheKey, true, self::MAX_AGE_SECONDS);
    }

    /**
     * Log security event for invalid signatures
     */
    private function logSecurityEvent(Request $request, string $eventType): void
    {
        \App\Models\SecurityEvent::create([
            'event_type' => $eventType,
            'severity' => 'high',
            'source' => 'request_signature',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => "Invalid request signature detected from {$request->ip()}",
            'event_data' => [
                'path' => $request->path(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
            ],
            'detected_at' => now(),
        ]);
    }
}
