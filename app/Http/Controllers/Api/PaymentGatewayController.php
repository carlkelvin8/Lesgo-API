<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function __construct(private PaymentGatewayService $gateway) {}

    /**
     * @OA\Post(
     *     path="/api/v1/gateway/initiate",
     *     summary="Create a PayMongo payment link",
     *     tags={"Payment Gateway"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"amount","description"},
     *             @OA\Property(property="amount", type="number", example=150.00),
     *             @OA\Property(property="description", type="string", example="LeSGo Order #42"),
     *             @OA\Property(property="remarks", type="string", example="Order payment")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Payment link created",
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="checkout_url", type="string"),
     *             @OA\Property(property="reference", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="amount", type="number")
     *         ))
     *     ),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'remarks'     => 'nullable|string|max:255',
        ]);

        try {
            $link = $this->gateway->createPaymentLink(
                $validated['amount'],
                $validated['description'],
                ['remarks' => $validated['remarks'] ?? null]
            );

            return $this->created($link, 'Payment link created');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/gateway/{type}/{id}",
     *     summary="Retrieve a PayMongo resource (links, payments, refunds)",
     *     tags={"Payment Gateway"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="type", in="path", required=true,
     *         @OA\Schema(type="string", enum={"links","payments","refunds"})
     *     ),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Resource data"),
     *     @OA\Response(response=502, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function retrieve(Request $request, string $type, string $id): JsonResponse
    {
        if (!in_array($type, ['links', 'payments', 'refunds'])) {
            return $this->error('Invalid resource type', 422);
        }

        try {
            $data = $this->gateway->retrieve($id, $type);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/gateway/refund",
     *     summary="Refund a PayMongo payment",
     *     tags={"Payment Gateway"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"payment_id","amount"},
     *             @OA\Property(property="payment_id", type="string", example="pay_abc123"),
     *             @OA\Property(property="amount", type="number", example=150.00),
     *             @OA\Property(property="reason", type="string", enum={"duplicate","fraudulent","others"}, example="others")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Refund created"),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function refund(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Only admins can issue refunds', 403);
        }

        $validated = $request->validate([
            'payment_id' => 'required|string',
            'amount'     => 'required|numeric|min:1',
            'reason'     => 'nullable|in:duplicate,fraudulent,others',
        ]);

        try {
            $refund = $this->gateway->refund(
                $validated['payment_id'],
                $validated['amount'],
                $validated['reason'] ?? 'others'
            );

            return $this->success($refund, 'Refund created successfully');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }
}
