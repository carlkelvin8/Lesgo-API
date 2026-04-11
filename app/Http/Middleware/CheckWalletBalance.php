<?php

namespace App\Http\Middleware;

use App\Services\WalletValidationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWalletBalance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only check wallet balance for drivers
        if (!$user || !$user->isDriver()) {
            return $next($request);
        }

        // Check if wallet balance is sufficient
        if (!WalletValidationService::hasSufficientBalance($user)) {
            $validation = WalletValidationService::validateBalance($user);
            
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance to perform this action.',
                'wallet_validation' => $validation,
                'error_code' => 'INSUFFICIENT_WALLET_BALANCE'
            ], 422);
        }

        return $next($request);
    }
}