<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Payment::query()->with(['order', 'customer', 'partner', 'driverProfile']);

        if (!$user->isAdmin()) {
            $query->where('customer_id', $user->id);
        }

        if ($orderId = $request->query('order_id')) {
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

        return $this->created($payment, 'Payment recorded successfully');
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        if (!$request->user()->isAdmin() && (int) $payment->customer_id !== (int) $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        $payment->load(['order', 'customer', 'partner', 'driverProfile']);

        return $this->success($payment);
    }
}
