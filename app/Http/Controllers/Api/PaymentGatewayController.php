<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CacheService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentGatewayController extends Controller
{
    public function __construct(private PaymentGatewayService $xendit) {}

    /**
     * @OA\Post(
     *     path="/api/v1/gateway/invoice",
     *     summary="Create a Xendit hosted invoice (GCash, Maya, cards, OTC, VA)",
     *     tags={"Payment Gateway"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"order_id","amount"},
     *             @OA\Property(property="order_id", type="integer", example=42),
     *             @OA\Property(property="amount", type="number", example=150.00),
     *             @OA\Property(property="description", type="string", example="LeSGo Order #42"),
     *             @OA\Property(property="success_redirect_url", type="string", example="https://app.lesgo.ph/payment/success"),
     *             @OA\Property(property="failure_redirect_url", type="string", example="https://app.lesgo.ph/payment/failed"),
     *             @OA\Property(property="payment_methods", type="array",
     *                 @OA\Items(type="string", enum={"GCASH","PAYMAYA","CREDIT_CARD","OTC","VIRTUAL_ACCOUNT","QRPH"}),
     *                 example={"GCASH","PAYMAYA"}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Invoice created",
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="invoice_url", type="string"),
     *             @OA\Property(property="external_id", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="amount", type="number"),
     *             @OA\Property(property="expiry_date", type="string")
     *         ))
     *     ),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=409, description="Order already paid"),
     *     @OA\Response(response=502, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function createInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'            => 'required|integer|exists:orders,id',
            'amount'              => 'required|numeric|min:1',
            'description'         => 'nullable|string|max:255',
            'success_redirect_url' => 'nullable|url',
            'failure_redirect_url' => 'nullable|url',
            'payment_methods'     => 'nullable|array',
            'payment_methods.*'   => 'string|in:GCASH,PAYMAYA,CREDIT_CARD,OTC,VIRTUAL_ACCOUNT,QRPH',
        ]);

        $user  = $request->user();
        $order = Order::findOrFail($validated['order_id']);

        // Ownership check
        if (!$user->isAdmin() && (int) $order->customer_id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        // Idempotency — don't create a second invoice if one is already pending
        $existing = Payment::where('order_id', $order->id)
            ->whereIn('status', ['paid', 'pending'])
            ->whereNotNull('provider_reference')
            ->first();

        if ($existing?->status === 'paid') {
            return $this->error('Order is already paid.', 409);
        }

        $externalId  = 'lesgo-order-' . $order->id . '-' . Str::random(6);
        $description = $validated['description'] ?? "LeSGo Order #{$order->id}";

        $meta = [
            'currency'            => 'PHP',
            'success_redirect_url' => $validated['success_redirect_url'] ?? null,
            'failure_redirect_url' => $validated['failure_redirect_url'] ?? null,
            'payment_methods'     => $validated['payment_methods'] ?? null,
        ];

        // Attach payer info if available
        if ($user->email) {
            $meta['payer_email'] = $user->email;
            $meta['payer_name']  = $user->name;
            $meta['payer_phone'] = $user->phone_number;
        }

        try {
            $invoice = $this->xendit->createInvoice($validated['amount'], $externalId, $description, $meta);

            // Record a pending payment row linked to this invoice
            $payment = Payment::updateOrCreate(
                ['order_id' => $order->id, 'status' => 'pending'],
                [
                    'customer_id'        => $order->customer_id,
                    'amount'             => $validated['amount'],
                    'currency'           => 'PHP',
                    'method'             => 'xendit',
                    'provider'           => 'xendit',
                    'provider_reference' => $invoice['id'],
                    'meta'               => ['external_id' => $externalId, 'invoice_url' => $invoice['invoice_url']],
                ]
            );

            CacheService::forgetByPattern("payments:user:{$order->customer_id}:list:*");
            CacheService::forgetByPattern("payments:admin:list:*");

            return $this->created([
                'invoice'    => $invoice,
                'payment_id' => $payment->id,
            ], 'Invoice created — redirect user to invoice_url');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/gateway/invoice/{invoiceId}",
     *     summary="Get Xendit invoice status",
     *     tags={"Payment Gateway"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="invoiceId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Invoice details"),
     *     @OA\Response(response=502, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function getInvoice(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $data = $this->xendit->getInvoice($invoiceId);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/gateway/invoice/{invoiceId}/expire",
     *     summary="Expire (cancel) a pending Xendit invoice",
     *     tags={"Payment Gateway"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="invoiceId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Invoice expired"),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function expireInvoice(Request $request, string $invoiceId): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return $this->error('Only admins can expire invoices', 403);
        }

        try {
            $data = $this->xendit->expireInvoice($invoiceId);
            return $this->success($data, 'Invoice expired');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/gateway/refund",
     *     summary="Refund a Xendit payment (admin only)",
     *     tags={"Payment Gateway"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"payment_request_id","amount"},
     *             @OA\Property(property="payment_request_id", type="string", example="ewc_abc123"),
     *             @OA\Property(property="amount", type="number", example=150.00),
     *             @OA\Property(property="reason", type="string",
     *                 enum={"DUPLICATE","FRAUDULENT","REQUESTED_BY_CUSTOMER"},
     *                 example="REQUESTED_BY_CUSTOMER"
     *             )
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
            'payment_request_id' => 'required|string',
            'amount'             => 'required|numeric|min:1',
            'reason'             => 'nullable|in:DUPLICATE,FRAUDULENT,REQUESTED_BY_CUSTOMER',
        ]);

        try {
            $refund = $this->xendit->createRefund(
                $validated['payment_request_id'],
                $validated['amount'],
                $validated['reason'] ?? 'REQUESTED_BY_CUSTOMER'
            );

            return $this->success($refund, 'Refund created successfully');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 502);
        }
    }
}
