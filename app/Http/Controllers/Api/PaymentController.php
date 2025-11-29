<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Payment",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="order_id", type="integer"),
 *     @OA\Property(property="customer_id", type="integer"),
 *     @OA\Property(property="amount", type="number", format="float", example=250.00),
 *     @OA\Property(property="currency", type="string", example="PHP"),
 *     @OA\Property(property="method", type="string", example="gcash"),
 *     @OA\Property(property="status", type="string", example="paid")
 * )
 */
class PaymentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/payments",
     *     summary="List payments",
     *     tags={"Payments"},
     *     @OA\Parameter(name="order_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", example="paid")),
     *     @OA\Response(response=200, description="List of payments")
     * )
     */
    public function index(Request $request)
    {
        $query = Payment::query()->with(['order', 'customer', 'partner', 'driverProfile']);

        if ($orderId = $request->query('order_id')) {
            $query->where('order_id', $orderId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderByDesc('id')->paginate(20));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments",
     *     summary="Create payment record",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             required={"order_id","customer_id","amount","method"},
     *             @OA\Property(property="order_id", type="integer"),
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="partner_id", type="integer", nullable=true),
     *             @OA\Property(property="driver_id", type="integer", nullable=true),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="currency", type="string", example="PHP"),
     *             @OA\Property(property="method", type="string", example="gcash"),
     *             @OA\Property(property="status", type="string", example="paid")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Payment created")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id'    => ['required', 'integer', 'exists:orders,id'],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'partner_id'  => ['nullable', 'integer'],
            'driver_id'   => ['nullable', 'integer'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'currency'    => ['nullable', 'string', 'max:3'],
            'method'      => ['required', 'string', 'max:50'],
            'status'      => ['nullable', 'string', 'max:50'],
            'provider'    => ['nullable', 'string', 'max:100'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'paid_at'     => ['nullable', 'date'],
            'meta'        => ['nullable', 'array'],
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

        return response()->json($payment, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/{id}",
     *     summary="Get payment by ID",
     *     tags={"Payments"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payment details")
     * )
     */
    public function show(Payment $payment)
    {
        $payment->load(['order', 'customer', 'partner', 'driverProfile']);

        return response()->json($payment);
    }
}
