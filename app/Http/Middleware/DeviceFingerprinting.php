<?php

namespace App\Http\Middleware;

use App\Services\DeviceFingerprintService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Device Fingerprint Tracking Middleware
 * 
 * Automatically fingerprints devices and tracks device usage.
 * Adds device fingerprint to request and logs device activity.
 */
class DeviceFingerprinting
{
    public function __construct(
        private DeviceFingerprintService $fingerprintService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Create device fingerprint
        $fingerprint = $this->fingerprintService->createFingerprint($request);
        
        // Add to request attributes
        $request->attributes->set('device_fingerprint', $fingerprint);

        // If user is authenticated, track device
        if ($request->user()) {
            $deviceData = $this->fingerprintService->getOrCreateUserFingerprint(
                $request,
                $request->user()->id
            );

            // Add device trust status to request
            $request->attributes->set('device_trusted', $deviceData['is_trusted'] ?? false);
            $request->attributes->set('device_suspicious', $deviceData['is_suspicious'] ?? false);

            // Check if device is suspicious and block if needed
            if ($deviceData['is_suspicious'] ?? false) {
                // Could block or require additional verification here
                // For now, just flag in request
            }
        }

        $response = $next($request);

        // Add fingerprint to response headers (for client to store)
        return $response->withHeaders([
            'X-Device-Fingerprint' => $fingerprint,
        ]);
    }
}
