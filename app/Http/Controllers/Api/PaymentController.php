<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Payment::query()->with(['order', 'customer']);

        // Hard scope — non-admins only see their own payments
        if (!$user->isAdmin()) {
            $query->where('customer_id', $user->id);
        }

        if ($orderId = $request->query('order_id')) {
            // Extra check: non-admin can only filter by their own orders
            if (!$user->isAdmin()) {
                $ownsOrder = Order::where('id', $orderId)
                    ->where('customer_id', $user->id)
                    ->exists();

                if (!$ownsOrder) {
                    return $this->error('Forbidden', 403);
                }
            }
            $query->where('order_id', $orderId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->success($query->orderByDesc('id')->paginate(20));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Verify the order belongs to the customer_id being paid for
        $order = Order::find($data['order_id']);

        if (!$user->isAdmin() && (int) $order->customer_id !== (int) $data['customer_id']) {
            return $this->error('Payment customer does not match order owner.', 403);
        }

        // Prevent duplicate paid payment on same order
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
            // Non-admins always start as pending — status/provider fields are prohibited in FormRequest
            'status'             => $data['status'] ?? 'pending',
            'provider'           => $data['provider'] ?? null,
            'provider_reference' => $data['provider_reference'] ?? null,
            'paid_at'            => $data['paid_at'] ?? null,
            'meta'               => $data['meta'] ?? null,
        ]);

        return $this->created($payment, 'Payment recorded successfully');
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && (int) $payment->customer_id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        $payment->load(['order', 'customer', 'partner', 'driverProfile']);

        return $this->success($payment);
    }
}
