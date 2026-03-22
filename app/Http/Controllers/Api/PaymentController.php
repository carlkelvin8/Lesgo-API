<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $orderId = $request->query('order_id');
        $status  = $request->query('status');

        // Non-admin ownership check on order_id filter
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

        // Bust list caches for this customer and admin
        CacheService::forgetByPattern("payments:user:{$data['customer_id']}:list:*");
        CacheService::forgetByPattern("payments:admin:list:*");

        return $this->created($payment, 'Payment recorded successfully');
    }

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
}
