<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        $allowed = $user->isAdmin()
            || (int) $user->id === (int) $order->customer_id
            || ($user->isDriver() && (int) optional($user->driverProfile)->id === (int) $order->driver_id);

        if (!$allowed) {
            return $this->error('Forbidden', 403);
        }

        $order->load(['service', 'customer', 'pickupAddress', 'dropoffAddress', 'lesbuyItems', 'payments']);

        $payment       = $order->payments->where('status', 'paid')->first();
        $transactionId = $payment?->provider_reference ?? 'N/A';
        $paidAt        = $payment?->paid_at ?? $order->completed_at;

        $itemsTotal = $order->lesbuyItems->sum(fn ($item) => ($item->estimated_price ?? 0) * $item->quantity);

        return $this->success([
            'order_id'       => $order->id,
            'transaction_id' => $transactionId,
            'date'           => $paidAt ?? $order->created_at,
            'customer_name'  => $order->customer?->name ?? 'Customer',
            'service'        => $order->service?->name ?? 'Service',
            'pickup'         => $order->pickupAddress?->address_line1 ?? ($order->meta['pickup']['address'] ?? 'N/A'),
            'dropoff'        => $order->dropoffAddress?->address_line1 ?? ($order->meta['dropoff']['address'] ?? 'N/A'),
            'distance_km'    => round(($order->actual_distance_m ?: $order->estimated_distance_m) / 1000, 2),
            'items'          => $order->lesbuyItems->map(fn ($item) => [
                'name'     => $item->name,
                'quantity' => $item->quantity,
                'price'    => $item->estimated_price,
                'total'    => $item->quantity * ($item->estimated_price ?? 0),
            ]),
            'breakdown' => [
                'base_fare'     => 40.00,
                'distance_fare' => max(0, ($order->actual_fare ?? $order->estimated_fare ?? 0) - 40.00 - $itemsTotal),
                'items_total'   => $itemsTotal,
                'total_amount'  => $order->actual_fare ?? $order->estimated_fare ?? 0,
            ],
            'payment_method' => $order->payment_method,
            'status'         => $order->status,
        ]);
    }
}
