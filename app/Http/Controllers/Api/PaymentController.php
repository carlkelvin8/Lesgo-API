<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/payments",
     *     summary="List payments (scoped by role)",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="order_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","paid","failed","refunded"})),
     *     @OA\Response(response=200, description="Paginated payments",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Payment")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
     *         )
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $orderId = $request->query('order_id');
        $status  = $request->query('status');

        if ($orderId && !$user->isAdmin()) {
            $ownsOrder = Order::where('id', $orderId)
                ->where('customer_id', $user->id)
                ->exists();

            if (!$ownsOrder) {
                return $this->error('Forbidden', 403);
            }
        }

        $scopeId  = $user->isAdmin() ? 'admin' : "user:{$user->id}";
        $cacheKey = "payments:{$scopeId}:list:" . md5("{$orderId}:{$status}");

        $paginator = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($user, $orderId, $status) {
            $query = Payment::query()->with(['order', 'customer']);

            if (!$user->isAdmin()) {
                $query->where('customer_id', $user->id);
            }

            if ($orderId) {
                $query->where('order_id', $orderId);
            }

            if ($status) {
                $query->where('status', $status);
            }

            return $query->orderByDesc('id')->paginate(20);
        });

        return $this->success($paginator);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments",
     *     summary="Record a payment",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"order_id","customer_id","amount","method"},
     *             @OA\Property(property="order_id", type="integer", example=10),
     *             @OA\Property(property="customer_id", type="integer", example=5),
     *             @OA\Property(property="amount", type="number", format="float", example=85.50),
     *             @OA\Property(property="currency", type="string", example="PHP"),
     *             @OA\Property(property="method", type="string", enum={"cash","gcash","maya","card","wallet"}, example="gcash")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Payment recorded",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Payment"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=409, description="Order already paid")
     * )
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $order = Order::find($data['order_id']);

        if (!$user->isAdmin() && (int) $order->customer_id !== (int) $data['customer_id']) {
            return $this->error('Payment customer does not match order owner.', 403);
        }

        $alreadyPaid = Payment::where('order_id', $data['order_id'])
            ->where('status', 'paid')
            ->exists();

        if ($alreadyPaid) {
            return $this->error('This order has already been paid.', 409);
        }

        $payment = Payment::create([
            'order_id'           => $data['order_id'],
            'customer_id'        => $data['customer_id'],
            'partner_id'         => $data['partner_id'] ?? null,
            'driver_id'          => $data['driver_id'] ?? null,
            'amount'             => $data['amount'],
            'currency'           => $data['currency'] ?? 'PHP',
            'method'             => $data['method'],
            'status'             => $data['status'] ?? 'pending',
            'provider'           => $data['provider'] ?? null,
            'provider_reference' => $data['provider_reference'] ?? null,
            'paid_at'            => $data['paid_at'] ?? null,
            'meta'               => $data['meta'] ?? null,
        ]);

        CacheService::forgetByPattern("payments:user:{$data['customer_id']}:list:*");
        CacheService::forgetByPattern("payments:admin:list:*");

        return $this->created($payment, 'Payment recorded successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/{id}",
     *     summary="Get payment by ID",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payment details",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Payment"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && (int) $payment->customer_id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        $cacheKey = "payments:payment:{$payment->id}";

        $payment = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($payment) {
            $payment->load(['order', 'customer', 'partner', 'driverProfile']);
            return $payment;
        });

        return $this->success($payment);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/webhooks/payments/{provider}",
     *     summary="Receive payment webhook from provider (GCash, Maya, PayMongo)",
     *     tags={"Payments"},
     *     @OA\Parameter(name="provider", in="path", required=true, @OA\Schema(type="string", enum={"gcash","maya","paymongo"})),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="reference", type="string"),
     *         @OA\Property(property="status", type="string")
     *     )),
     *     @OA\Response(response=200, description="Webhook accepted"),
     *     @OA\Response(response=400, description="Invalid signature")
     * )
     */
    public function webhook(Request $request, string $provider): JsonResponse
    {
        if (!$this->verifyWebhookSignature($request, $provider)) {
            return $this->error('Invalid webhook signature', 400);
        }

        ProcessPaymentWebhookJob::dispatch($provider, $request->all())->onQueue('default');

        return $this->message('Webhook received');
    }

    private function verifyWebhookSignature(Request $request, string $provider): bool
    {
        $secret = config("services.{$provider}.webhook_secret");

        if (!$secret) {
            return true;
        }

        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('X-PayMongo-Signature')
            ?? '';

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
