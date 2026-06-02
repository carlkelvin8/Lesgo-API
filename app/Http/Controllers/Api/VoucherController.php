<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/vouchers/available",
     *     summary="Get available vouchers for current user",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Available vouchers",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="code", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="discount_text", type="string"),
     *                 @OA\Property(property="min_order", type="string"),
     *                 @OA\Property(property="expires_at", type="string"),
     *                 @OA\Property(property="eligible", type="boolean")
     *             ))
     *         )
     *     )
     * )
     */
    public function getAvailableVouchers(Request $request, VoucherService $voucherService): JsonResponse
    {
        $user = $request->user();
        $vouchers = $voucherService->getAvailableVouchers($user);
        
        return $this->success($vouchers, 'Available vouchers retrieved successfully');
    }
    
    /**
     * @OA\Post(
     *     path="/api/v1/vouchers/validate",
     *     summary="Validate voucher code for current user",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="voucher_code", type="string", example="WELCOME10"),
     *             @OA\Property(property="order_value", type="number", format="float", example=150.00)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Voucher validation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="valid", type="boolean"),
     *                 @OA\Property(property="discount_amount", type="number", format="float"),
     *                 @OA\Property(property="discount_type", type="string"),
     *                 @OA\Property(property="new_total", type="number", format="float"),
     *                 @OA\Property(property="error", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function validateVoucher(Request $request, VoucherService $voucherService): JsonResponse
    {
        $validated = $request->validate([
            'voucher_code' => 'required|string|max:20',
            'order_value' => 'required|numeric|min:0'
        ]);
        
        // Create a temporary order object for validation
        $tempOrder = new \App\Models\Order([
            'customer_id' => $request->user()->id,
            'estimated_fare' => $validated['order_value'],
            'service_id' => 1 // Default service for validation
        ]);
        
        // Use a separate validation method that doesn't update the order
        $result = $voucherService->validateVoucherOnly($tempOrder, $validated['voucher_code']);
        
        if ($result['valid']) {
            return $this->success([
                'valid' => true,
                'discount_amount' => $result['discount_amount'],
                'discount_type' => $result['discount_type'],
                'new_total' => $result['new_total'],
                'voucher_details' => $result['voucher_details']
            ], 'Voucher is valid');
        } else {
            return $this->success([
                'valid' => false,
                'error' => $result['error']
            ], 'Voucher validation result');
        }
    }

    /**
     * Claim a voucher from the offers screen.
     */
    public function claimVoucher(Request $request, VoucherService $voucherService): JsonResponse
    {
        $validated = $request->validate([
            'voucher_code' => 'required|string|max:20',
        ]);

        $result = $voucherService->claimVoucher(
            $request->user(),
            $validated['voucher_code']
        );

        if (empty($result['success'])) {
            return $this->error($result['error'] ?? 'Unable to claim voucher', 422);
        }

        return $this->success($result, 'Voucher claimed successfully');
    }
}