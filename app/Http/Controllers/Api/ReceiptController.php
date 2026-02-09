<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * GET /api/v1/orders/{order}/receipt
     */
    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        // Ensure user is authorized to view this receipt
        if ($user->id !== $order->customer_id && !$user->isAdmin()) {
             // If driver, check if assigned
             if ($user->isDriver()) {
                 $driverProfileId = optional($user->driverProfile)->id;
                 if ((int)$order->driver_id !== (int)$driverProfileId) {
                     return response()->json(['message' => 'Forbidden.'], 403);
                 }
             } else {
                 return response()->json(['message' => 'Forbidden.'], 403);
             }
        }

        $order->load(['service', 'pickupAddress', 'dropoffAddress', 'lesbuyItems', 'payments']);

        $payment = $order->payments->where('status', 'paid')->first();
        $transactionId = $payment ? $payment->provider_reference : 'N/A';
        $paidAt = $payment ? $payment->paid_at : $order->completed_at;

        // Calculate Items Total
        $itemsTotal = 0;
        foreach ($order->lesbuyItems as $item) {
            // Use actual price if available (maybe add 'actual_price' later), or estimated
            $price = $item->estimated_price ?? 0;
            $itemsTotal += $price * $item->quantity;
        }

        // Breakdown
        $breakdown = [
            'base_fare'      => 40.00, // This should ideally be retrieved from service or order logic
            'distance_fare'  => max(0, $order->actual_fare - 40.00 - $itemsTotal), // Rough estimate
            'items_total'    => $itemsTotal,
            'total_amount'   => $order->actual_fare > 0 ? $order->actual_fare : $order->estimated_fare,
        ];

        // Refine breakdown if we can reverse engineer calculateFare logic or store it
        // For now, let's just present what we have.
        // If it's Lesbuy, the fare usually includes the service fee. The items cost might be separate or included?
        // In many "pabili" apps, the "fare" is the delivery fee, and "items" are separate.
        // But in OrderController, calculateFare adds flat fee based on order_value.
        
        $receipt = [
            'order_id'       => $order->id,
            'transaction_id' => $transactionId,
            'date'           => $paidAt ?? $order->created_at,
            'customer_name'  => $order->customer->name ?? 'Customer', // Load customer if needed
            'service'        => $order->service->name ?? 'Service',
            'pickup'         => $order->pickupAddress->address_line1 ?? 'N/A',
            'dropoff'        => $order->dropoffAddress->address_line1 ?? 'N/A',
            'distance_km'    => ($order->actual_distance_m ?: $order->estimated_distance_m) / 1000,
            'items'          => $order->lesbuyItems->map(function($item) {
                return [
                    'name'     => $item->name,
                    'quantity' => $item->quantity,
                    'price'    => $item->estimated_price,
                    'total'    => $item->quantity * $item->estimated_price,
                ];
            }),
            'breakdown'      => $breakdown,
            'payment_method' => $order->payment_method,
            'status'         => $order->status,
        ];

        return response()->json($receipt);
    }
}
