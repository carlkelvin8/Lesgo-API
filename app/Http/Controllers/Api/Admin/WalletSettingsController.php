<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecuritySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function ($request, $next) {
            if (!$request->user() || !$request->user()->isAdmin()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            return $next($request);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/wallet-settings/threshold",
     *     summary="Get minimum wallet threshold (Admin only)",
     *     tags={"Admin - Wallet Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Current threshold",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="minimum_threshold", type="number", format="float", example=100.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function getThreshold(): JsonResponse
    {
        $threshold = SecuritySetting::getValue('wallet.minimum_threshold', 100.00);
        
        return $this->success([
            'minimum_threshold' => (float) $threshold
        ], 'Minimum threshold retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/wallet-settings/threshold",
     *     summary="Update minimum wallet threshold (Admin only)",
     *     tags={"Admin - Wallet Settings"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="threshold", type="number", format="float", example=125.00)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Threshold updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="minimum_threshold", type="number", format="float", example=125.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function updateThreshold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threshold' => 'required|numeric|min:0|max:10000'
        ]);

        SecuritySetting::setValue('wallet.minimum_threshold', $validated['threshold']);
        
        return $this->success([
            'minimum_threshold' => (float) $validated['threshold']
        ], 'Minimum threshold updated successfully');
    }
}