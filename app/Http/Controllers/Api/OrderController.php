<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="LeSGo Platform API",
 *     version="1.0.0",
 *     description="Logistics & multi-service API for LeSGo (customers, drivers, partners)."
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Local development server"
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="customer_id", type="integer", example=10),
 *     @OA\Property(property="partner_id", type="integer", nullable=true, example=2),
 *     @OA\Property(property="driver_id", type="integer", nullable=true, example=5),
 *     @OA\Property(property="service_id", type="integer", example=1),
 *     @OA\Property(property="pickup_address_id", type="integer", nullable=true, example=100),
 *     @OA\Property(property="dropoff_address_id", type="integer", nullable=true, example=101),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="picked_up_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="estimated_distance_m", type="integer", nullable=true, example=12000),
 *     @OA\Property(property="actual_distance_m", type="integer", nullable=true, example=11500),
 *     @OA\Property(property="estimated_fare", type="number", format="float", nullable=true, example=250.50),
 *     @OA\Property(property="actual_fare", type="number", format="float", nullable=true, example=240.00),
 *     @OA\Property(property="partner_share", type="number", format="float", nullable=true, example=100.00),
 *     @OA\Property(property="driver_share", type="number", format="float", nullable=true, example=120.00),
 *     @OA\Property(property="platform_fee", type="number", format="float", nullable=true, example=20.00),
 *     @OA\Property(property="payment_method", type="string", example="cash"),
 *     @OA\Property(property="payment_status", type="string", example="pending"),
 *     @OA\Property(property="cancel_reason", type="string", nullable=true),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(
 *             property="order_value",
 *             type="number",
 *             format="float",
 *             example=750.00,
 *             description="Order/cart value used for LeSBuy / LeSEat extra fee"
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class OrderController extends Controller
{
    /**
     * List orders with optional filters.
     *
     * @OA\Get(
     *     path="/api/v1/orders",
     *     summary="List orders",
     *     description="List orders with optional filters (status, customer_id, driver_id, partner_id).",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         required=false,
     *         @OA\Schema(type="string", example="pending")
     *     ),
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         description="Filter by customer ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         description="Filter by driver profile ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="partner_id",
     *         in="query",
     *         description="Filter by partner ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of orders",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Order")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Order::query()->with([
            'customer',
            'partner',
            'driverProfile',
            'service',
            'pickupAddress',
            'dropoffAddress',
        ]);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($driverId = $request->query('driver_id')) {
            $query->where('driver_id', $driverId);
        }

        if ($partnerId = $request->query('partner_id')) {
            $query->where('partner_id', $partnerId);
        }

        $orders = $query->orderByDesc('id')->paginate(20);

        return response()->json($orders);
    }

    /**
     * Create a new order.
     *
     * Business rules:
     * - Distance is provided as meters (estimated_distance_m).
     * - Max distance: 30 km (30000 m).
     * - Services:
     *   - LESGO  : base 40 (first 3km), then 9.5 / km
     *   - LESBUY : base 40 (first 3km), then 10 / km + value-based fee
     *   - LESEAT : base 40 (first 3km), then 10 / km + value-based fee
     * - Value-based fee (LeSBuy / LeSEat), using meta.order_value:
     *   - 0–500   : +15
     *   - 501–1000: +30
     *   - >1000   : +45
     *
     * @OA\Post(
     *     path="/api/v1/orders",
     *     summary="Create order",
     *     tags={"Orders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id","service_id","pickup_address_id","dropoff_address_id","estimated_distance_m"},
     *             @OA\Property(property="customer_id", type="integer", example=10),
     *             @OA\Property(property="partner_id", type="integer", nullable=true, example=2),
     *             @OA\Property(property="driver_id", type="integer", nullable=true, example=5),
     *             @OA\Property(property="service_id", type="integer", example=1),
     *             @OA\Property(property="pickup_address_id", type="integer", example=100),
     *             @OA\Property(property="dropoff_address_id", type="integer", example=101),
     *             @OA\Property(
     *                 property="estimated_distance_m",
     *                 type="integer",
     *                 example=12000,
     *                 description="Estimated distance in meters (max 30000 => 30km)"
     *             ),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="payment_method", type="string", example="cash"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(
     *                     property="order_value",
     *                     type="number",
     *                     format="float",
     *                     example=750.00,
     *                     description="Cart/food value used for LeSBuy / LeSEat additional fee"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'          => ['required', 'integer', 'exists:users,id'],
            'partner_id'           => ['nullable', 'integer', 'exists:partners,id'],
            'driver_id'            => ['nullable', 'integer', 'exists:driver_profiles,id'],
            'service_id'           => ['required', 'integer', 'exists:services,id'],
            'pickup_address_id'    => ['required', 'integer', 'exists:addresses,id'],
            'dropoff_address_id'   => ['required', 'integer', 'exists:addresses,id'],
            'status'               => ['nullable', 'string'],
            'scheduled_at'         => ['nullable', 'date'],
            'estimated_distance_m' => ['required', 'integer', 'min:1', 'max:30000'], // 1m–30km
            'payment_method'       => ['nullable', 'string'],
            'payment_status'       => ['nullable', 'string'],
            'meta'                 => ['nullable', 'array'],
        ]);

        // Load service to get code (LESGO / LESBUY / LESEAT)
        /** @var Service $service */
        $service = Service::findOrFail($data['service_id']);
        $serviceCode = strtoupper((string) $service->code);

        // Distance in km (capped inside calculateFare as well)
        $distanceKm = $data['estimated_distance_m'] / 1000;

        // Extract order_value from meta if exists
        $meta = $data['meta'] ?? [];
        $orderValue = 0.0;
        if (!empty($meta['order_value'])) {
            $orderValue = (float) $meta['order_value'];
        }

        // Compute estimated fare
        $estimatedFare = $this->calculateFare($serviceCode, $distanceKm, $orderValue);

        $order = Order::create([
            'customer_id'          => $data['customer_id'],
            'partner_id'           => $data['partner_id'] ?? null,
            'driver_id'            => $data['driver_id'] ?? null,
            'service_id'           => $data['service_id'],
            'pickup_address_id'    => $data['pickup_address_id'],
            'dropoff_address_id'   => $data['dropoff_address_id'],
            'status'               => $data['status'] ?? 'pending',
            'scheduled_at'         => $data['scheduled_at'] ?? null,
            'estimated_distance_m' => $data['estimated_distance_m'],
            'estimated_fare'       => $estimatedFare,
            'payment_method'       => $data['payment_method'] ?? 'cash',
            'payment_status'       => $data['payment_status'] ?? 'pending',
            'meta'                 => $meta ?: null,
        ]);

        return response()->json($order->fresh(), 201);
    }

    /**
     * Show a single order.
     *
     * @OA\Get(
     *     path="/api/v1/orders/{id}",
     *     summary="Get order by ID",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order details",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Order $order)
    {
        $order->load([
            'customer',
            'partner',
            'driverProfile',
            'service',
            'pickupAddress',
            'dropoffAddress',
            'payments',
        ]);

        return response()->json($order);
    }

    /**
     * Update status / payment_status of an order and (optionally) actual fare.
     *
     * - If `actual_distance_m` is provided, we compute `actual_fare` using the same
     *   fare matrix (service.code + meta.order_value).
     *
     * @OA\Patch(
     *     path="/api/v1/orders/{id}/status",
     *     summary="Update order status / payment status",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="accepted",
     *                 description="pending|searching_driver|accepted|picked_up|completed|cancelled"
     *             ),
     *             @OA\Property(
     *                 property="payment_status",
     *                 type="string",
     *                 example="paid",
     *                 description="pending|paid|failed|refunded"
     *             ),
     *             @OA\Property(
     *                 property="cancel_reason",
     *                 type="string",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="actual_distance_m",
     *                 type="integer",
     *                 nullable=true,
     *                 example=13500,
     *                 description="Actual distance in meters (used for actual_fare). Max 30000."
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status'            => ['nullable', 'string', Rule::in([
                'pending',
                'searching_driver',
                'accepted',
                'picked_up',
                'completed',
                'cancelled',
            ])],
            'payment_status'    => ['nullable', 'string', Rule::in([
                'pending',
                'paid',
                'failed',
                'refunded',
            ])],
            'cancel_reason'     => ['nullable', 'string', 'max:255'],
            'actual_distance_m' => ['nullable', 'integer', 'min:1', 'max:30000'],
        ]);

        if (isset($data['status'])) {
            $order->status = $data['status'];

            if ($data['status'] === 'accepted') {
                $order->accepted_at = now();
            } elseif ($data['status'] === 'picked_up') {
                $order->picked_up_at = now();
            } elseif ($data['status'] === 'completed') {
                $order->completed_at = now();
            } elseif ($data['status'] === 'cancelled') {
                $order->cancelled_at = now();
            }
        }

        if (isset($data['payment_status'])) {
            $order->payment_status = $data['payment_status'];
        }

        if (array_key_exists('cancel_reason', $data)) {
            $order->cancel_reason = $data['cancel_reason'];
        }

        // Optional: compute actual fare if actual_distance_m is provided
        if (isset($data['actual_distance_m'])) {
            $order->actual_distance_m = $data['actual_distance_m'];

            $service = $order->service; // via relationship
            if ($service) {
                $serviceCode = strtoupper((string) $service->code);
                $distanceKm  = $order->actual_distance_m / 1000;

                $meta = $order->meta ?? [];
                $orderValue = 0.0;
                if (!empty($meta['order_value'])) {
                    $orderValue = (float) $meta['order_value'];
                }

                $order->actual_fare = $this->calculateFare($serviceCode, $distanceKm, $orderValue);
            }
        }

        $order->save();

        return response()->json($order->fresh());
    }

    /**
     * Fare calculation for LeSGo / LeSBuy / LeSEat.
     *
     * Rules:
     * - Limit distance to 30 km.
     * - Base 40 pesos covers first 3 km.
     * - LESGO  : 9.5 per km after 3 km.
     * - LESBUY : 10 per km after 3 km + value-based fee.
     * - LESEAT : 10 per km after 3 km + value-based fee.
     *
     * Value-based fee (LeSBuy / LeSEat):
     * - 0–500   => +15
     * - 501–1000=> +30
     * - >1000   => +45
     */
    private function calculateFare(string $serviceCode, float $distanceKm, float $orderValue = 0.0): float
    {
        $serviceCode = strtoupper($serviceCode);

        // Cap distance between 0 and 30 km
        $distanceKm = max(0, min($distanceKm, 30));

        if ($distanceKm <= 0) {
            return 0.0;
        }

        $baseFare       = 40.0;
        $firstKmCovered = 3.0;
        $perKmLeSGo     = 9.5;
        $perKmOthers    = 10.0;

        // Base part
        if ($distanceKm <= $firstKmCovered) {
            $fare = $baseFare;
        } else {
            $extraKm = $distanceKm - $firstKmCovered;

            if ($serviceCode === 'LESGO') {
                $fare = $baseFare + $extraKm * $perKmLeSGo;
            } else {
                // LESBUY / LESEAT / others default to 10 per km
                $fare = $baseFare + $extraKm * $perKmOthers;
            }
        }

        // Value-based fee for LeSBuy / LeSEat
        if (in_array($serviceCode, ['LESBUY', 'LESEAT'], true)) {
            if ($orderValue <= 500) {
                $fare += 15;
            } elseif ($orderValue <= 1000) {
                $fare += 30;
            } else {
                $fare += 45;
            }
        }

        return round($fare, 2);
    }
}
