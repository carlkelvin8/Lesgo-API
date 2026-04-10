<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpAccessControl
{
    private SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request with IP access control
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Check if IP is allowed
        if (!$this->securityService->isIpAllowed($ip)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied from your IP address.',
                'request_id' => $request->header('X-Request-ID'),
                'error' => [
                    'code' => 'IP_ACCESS_DENIED',
                    'ip_address' => $ip,
                ],
            ], 403);
        }

        return $next($request);
    }
}