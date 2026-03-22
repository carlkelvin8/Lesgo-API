<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Payment::query()->with(['order', 'customer', 'partner', 'driverProfile']);

        // Scope: customers see only their own payments, admins see all
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'           => ['required', 'integer', 'exists:orders,id'],
            'customer_id'        => ['required', 'integer', 'exists:users,id'],
            'partner_id'         => ['nullable', 'integer'],
            'driver_id'          => ['nullable', 'integer'],
            'amount'             => ['required', 'numeric', 'min:0'],
            'currency'           => ['nullable', 'string', 'max:3'],
            'method'             => ['required', 'string', 'max:50'],
            'status'             => ['nullable', 'string', 'max:50'],
            'provider'           => ['nullable', 'string', 'max:100'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'paid_at'            => ['nullable', 'date'],
            'meta'               => ['nullable', 'array'],
        ]);

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
        $user = $request->user();

        // Only owner or admin can view a specific payment
        if (!$user->isAdmin() && (int) $payment->customer_id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        $payment->load(['order', 'customer', 'partner', 'driverProfile']);

        return $this->success($payment);
    }
}
