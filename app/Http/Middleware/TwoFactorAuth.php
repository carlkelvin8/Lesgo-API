<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuth
{
    private TwoFactorAuthService $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Handle an incoming request requiring 2FA verification
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'request_id' => $request->header('X-Request-ID'),
            ], 401);
        }

        // Check if user has 2FA enabled
        if (!$this->twoFactorService->has2FAEnabled($user)) {
            return response()->json([
                'success' => false,
                'message' => '2FA is required but not enabled for your account',
                'request_id' => $request->header('X-Request-ID'),
                'error' => [
                    'code' => '2FA_REQUIRED',
                    'setup_url' => '/api/v1/security/2fa/setup',
                ],
            ], 403);
        }

        // Check if 2FA verification is provided
        $twoFactorCode = $request->header('X-2FA-Code') ?? $request->input('two_factor_code');
        
        if (!$twoFactorCode) {
            return response()->json([
                'success' => false,
                'message' => '2FA verification code required',
                'request_id' => $request->header('X-Request-ID'),
                'error' => [
                    'code' => '2FA_CODE_REQUIRED',
                    'methods' => $this->twoFactorService->get2FAMethods($user),
                ],
            ], 403);
        }

        // Verify 2FA code
        $verified = $this->twoFactorService->verifyTotp($user, $twoFactorCode) ||
                   $this->twoFactorService->verifyBackupCode($user, $twoFactorCode);

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid 2FA verification code',
                'request_id' => $request->header('X-Request-ID'),
                'error' => [
                    'code' => '2FA_VERIFICATION_FAILED',
                ],
            ], 403);
        }

        return $next($request);
    }
}