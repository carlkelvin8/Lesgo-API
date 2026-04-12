<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Versioning Middleware
 * 
 * Handles API version validation and routing based on Accept header or URL prefix.
 * Supports semantic versioning (v1, v2, etc.) and deprecation warnings.
 */
class ApiVersioning
{
    /**
     * Supported API versions
     */
    private const SUPPORTED_VERSIONS = ['v1'];

    /**
     * Deprecated versions (still working but will be removed)
     */
    private const DEPRECATED_VERSIONS = [];

    /**
     * Latest API version
     */
    private const LATEST_VERSION = 'v1';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract version from URL prefix (e.g., /api/v1/orders)
        $versionFromUrl = $this->extractVersionFromUrl($request);
        
        // Extract version from Accept header (e.g., application/vnd.lesgo.v1+json)
        $versionFromHeader = $this->extractVersionFromHeader($request);
        
        // Use URL version if available, otherwise header version, otherwise default to v1
        $version = $versionFromUrl ?? $versionFromHeader ?? self::LATEST_VERSION;

        // Check if version is supported
        if (!in_array($version, self::SUPPORTED_VERSIONS)) {
            if (in_array($version, self::DEPRECATED_VERSIONS)) {
                // Version is deprecated but still working
                return $next($request)->withHeaders([
                    'X-API-Deprecation' => "API version {$version} is deprecated. Please upgrade to " . self::LATEST_VERSION,
                    'X-API-Deprecation-Date' => $this->getDeprecationDate($version),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "API version {$version} is not supported",
                'supported_versions' => self::SUPPORTED_VERSIONS,
                'latest_version' => self::LATEST_VERSION,
                'documentation_url' => config('app.url') . '/api/docs',
            ], 400);
        }

        // Add version to request for downstream use
        $request->attributes->set('api_version', $version);

        // Add version headers to response
        $response = $next($request);
        
        return $response->withHeaders([
            'X-API-Version' => $version,
            'X-API-Latest-Version' => self::LATEST_VERSION,
        ]);
    }

    /**
     * Extract version from URL
     */
    private function extractVersionFromUrl(Request $request): ?string
    {
        $segments = $request->segments();
        
        // Look for 'v1', 'v2', etc. in URL
        foreach ($segments as $segment) {
            if (preg_match('/^v\d+$/', $segment)) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * Extract version from Accept header
     */
    private function extractVersionFromHeader(Request $request): ?string
    {
        $acceptHeader = $request->header('Accept');
        
        if (preg_match('/application\/vnd\.lesgo\.(v\d+)\+json/', $acceptHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get deprecation date for a version
     */
    private function getDeprecationDate(string $version): string
    {
        // Configure deprecation dates in config
        return config('api.deprecation_dates.' . $version, '2026-12-31');
    }
}
