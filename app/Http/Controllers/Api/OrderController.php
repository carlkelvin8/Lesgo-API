<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Jobs\NotifyDriverAssignedJob;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\Address;
use App\Models\Order;
use App\Models\Service;
use App\Services\CacheService;
use App\Services\RealtimeService;
use App\Services\WalletValidationService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private RealtimeService $realtimeService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/orders",
     *     summary="List orders (scoped by role)",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","searching_driver","accepted","picked_up","completed","cancelled"})),
     *     @OA\Parameter(name="payment_status", in="query", required=false, @OA\Schema(type="string", enum={"pending","paid","failed"})),
     *     @OA\Parameter(name="service_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated orders",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Order")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function index(FilterOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $cacheKey = "orders:user:{$user->id}:list:" . md5(serialize($validated));

        $paginator = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($validated, $user) {
            $query = $this->scopedOrdersQuery($user)->with([
                'customer:id,name,email,phone_number',
                'partner:id,name',
                'driverProfile:id,user_id,status,rating',
                'service:id,name,code,icon_url',
                'lesbuyItems:id,order_id,name,quantity,unit,estimated_price,actual_price,image_url,status',
            ]);

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            if (!empty($validated['payment_status'])) {
                $query->where('payment_status', $validated['payment_status']);
            }

            if (!empty($validated['service_id'])) {
                $query->where('service_id', (int) $validated['service_id']);
            }

            $perPage = (int) ($validated['per_page'] ?? 20);

            return $query->orderByDesc('id')->paginate($perPage);
        });

        return $this->success($paginator);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/orders",
     *     summary="Create a new order",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"service_id","pickup","dropoff","estimated_distance_m"},
     *             @OA\Property(property="service_id", type="integer", example=1),
     *             @OA\Property(property="pickup", type="object",
     *                 @OA\Property(property="address", type="string", example="123 Rizal St"),
     *                 @OA\Property(property="lat", type="number", example=14.5995),
     *                 @OA\Property(property="lng", type="number", example=120.9842)
     *             ),
     *             @OA\Property(property="dropoff", type="object",
     *                 @OA\Property(property="address", type="string", example="456 Mabini Ave"),
     *                 @OA\Property(property="lat", type="number", example=14.6090),
     *                 @OA\Property(property="lng", type="number", example=121.0000)
     *             ),
     *             @OA\Property(property="estimated_distance_m", type="integer", example=5200),
     *             @OA\Property(property="payment_method", type="string", enum={"cash","gcash","maya","card","wallet"}, example="cash"),
     *             @OA\Property(property="save_addresses", type="boolean", example=false),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Order created",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Order"))
     *     ),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function store(StoreOrderRequest $request, VoucherService $voucherService): JsonResponse
    {
        $data    = $request->validated();
        $user    = $request->user();
        $service = Service::findOrFail($data['service_id']);

        $serviceCode   = strtoupper((string) ($service->code ?? 'LESGO'));
        $distanceKm    = $data['estimated_distance_m'] / 1000;
        $meta          = $data['meta'] ?? [];
        $orderValue    = (float) ($meta['order_value'] ?? 0);
        $weightKg      = (float) ($data['estimated_weight_kg'] ?? 0);
        $fareBreakdown = $this->buildFareBreakdown($serviceCode, $distanceKm, $orderValue, $weightKg, $service);
        $saveAddresses = (bool) ($data['save_addresses'] ?? false);

        $order = DB::transaction(function () use ($user, $data, $fareBreakdown, $meta, $saveAddresses) {
            $pickupAddressId  = null;
            $dropoffAddressId = null;

            // Optionally save addresses to the user's address book
            if ($saveAddresses) {
                $pickupAddress = Address::create([
                    'user_id'       => $user->id,
                    'label'         => $data['pickup_label'] ?? 'Pickup',
                    'contact_name'  => $data['pickup']['contact_name'] ?? $user->name,
                    'contact_phone' => $data['pickup']['contact_phone'] ?? $user->phone_number,
                    'address_line1' => $data['pickup']['address'],
                    'country'       => 'PH',
                    'latitude'      => $data['pickup']['lat'],
                    'longitude'     => $data['pickup']['lng'],
                    'is_default'    => false,
                ]);

                $dropoffAddress = Address::create([
                    'user_id'       => $user->id,
                    'label'         => $data['dropoff_label'] ?? 'Dropoff',
                    'contact_name'  => $data['dropoff']['contact_name'] ?? $user->name,
                    'contact_phone' => $data['dropoff']['contact_phone'] ?? $user->phone_number,
                    'address_line1' => $data['dropoff']['address'],
                    'country'       => 'PH',
                    'latitude'      => $data['dropoff']['lat'],
                    'longitude'     => $data['dropoff']['lng'],
                    'is_default'    => false,
                ]);

                $pickupAddressId  = $pickupAddress->id;
                $dropoffAddressId = $dropoffAddress->id;
            }

            return Order::create([
                'customer_id'          => $user->id,
                'partner_id'           => null,
                'driver_id'            => null,
                'service_id'           => $data['service_id'],
                // Saved address IDs (optional)
                'pickup_address_id'    => $pickupAddressId,
                'dropoff_address_id'   => $dropoffAddressId,
                // Inline address fields (always stored)
                'pickup_address'       => $data['pickup']['address'],
                'pickup_lat'           => $data['pickup']['lat'],
                'pickup_lng'           => $data['pickup']['lng'],
                'pickup_contact_name'  => $data['pickup']['contact_name'] ?? $user->name,
                'pickup_contact_phone' => $data['pickup']['contact_phone'] ?? $user->phone_number,
                'dropoff_address'      => $data['dropoff']['address'],
                'dropoff_lat'          => $data['dropoff']['lat'],
                'dropoff_lng'          => $data['dropoff']['lng'],
                'dropoff_contact_name' => $data['dropoff']['contact_name'] ?? $user->name,
                'dropoff_contact_phone'=> $data['dropoff']['contact_phone'] ?? $user->phone_number,
                // Order details
                'notes'                => $data['notes'] ?? null,
                'item_description'     => $data['item_description'] ?? null,
                'estimated_weight_kg'  => $data['estimated_weight_kg'] ?? null,
                'vehicle_type'         => $data['vehicle_type'] ?? null,
                'passenger_name'       => $data['passenger_name'] ?? $user->name,
                'voucher_code'         => $data['voucher_code'] ?? null,
                'discount_amount'      => 0, // Will be calculated after voucher application
                'status'               => 'pending',
                'scheduled_at'         => $data['scheduled_at'] ?? null,
                'estimated_distance_m' => $data['estimated_distance_m'],
                'estimated_fare'       => $fareBreakdown['total'],
                'fare_breakdown'       => $fareBreakdown,
                'payment_method'       => $data['payment_method'] ?? 'cash',
                'payment_status'       => 'pending',
                'meta'                 => $meta ?: null,
            ]);
        });

        // Create order items
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $order->lesbuyItems()->create([
                    'name'              => $item['name'],
                    'quantity'          => $item['quantity'],
                    'unit'              => $item['unit'] ?? null,
                    'notes'             => $item['notes'] ?? null,
                    'image_url'         => $item['image_url'] ?? null,
                    'estimated_price'   => $item['estimated_price'] ?? null,
                    'is_checklist_item' => $item['is_checklist_item'] ?? false,
                    'status'            => 'pending',
                ]);
            }
        }

        // Apply voucher if provided
        if (!empty($data['voucher_code'])) {
            $voucherResult = $voucherService->applyVoucher($order, $data['voucher_code']);
            if (!$voucherResult['valid']) {
                return $this->error($voucherResult['error'], 422);
            }
        }

        $order->load([
            'customer:id,name,email,phone_number',
            'service:id,name,code,icon_url',
            'lesbuyItems',
            'payments:id,order_id,amount,status,method',
        ]);

        // Bust the order list cache for this customer
        CacheService::forgetByPattern("orders:user:{$user->id}:list:*");

        // Queue confirmation notification
        SendOrderConfirmationJob::dispatch($order)->onQueue('notifications');
        
        // Queue auto-assignment (if enabled)
        if ($this->shouldAutoAssignDriver($order)) {
            \App\Jobs\AutoAssignDriverJob::dispatch($order)->onQueue('driver-assignment');
        }

        return $this->created($order, 'Order created successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/{id}",
     *     summary="Get order by ID",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order details",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Order"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if (!$this->canViewOrder($request->user(), $order)) {
            return $this->error('Forbidden', 403);
        }

        $cacheKey = "orders:order:{$order->id}";

        $order = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($order) {
            $order->load([
                'customer:id,name,email,phone_number',
                'partner:id,name',
                'driverProfile:id,user_id,status,rating,last_latitude,last_longitude',
                'service:id,name,code,icon_url',
                'payments:id,order_id,amount,status,method,paid_at',
                'lesbuyItems:id,order_id,name,quantity,unit,notes,image_url,estimated_price,actual_price,is_checklist_item,status',
            ]);
            return $order;
        });

        return $this->success($order);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/orders/{id}/status",
     *     summary="Update order status",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"searching_driver","accepted","picked_up","completed","cancelled"}),
     *             @OA\Property(property="payment_status", type="string", enum={"pending","paid","failed"}),
     *             @OA\Property(property="cancel_reason", type="string", nullable=true),
     *             @OA\Property(property="actual_distance_m", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order updated",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Order"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=409, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (!$this->canUpdateOrder($user, $order)) {
            return $this->error('Forbidden', 403);
        }

        $data = $request->validated();

        if (isset($data['status'])) {
            $newStatus = $data['status'];

            if (!$this->isStatusChangeAllowed($user, $order, $newStatus)) {
                return $this->error('Status change not allowed for your role or current order state.', 403);
            }

            if ($newStatus === 'accepted') {
                if (!$user->isDriver()) {
                    return $this->error('Only drivers can accept orders.', 403);
                }

                // Check wallet balance before allowing acceptance
                if (!WalletValidationService::hasSufficientBalance($user)) {
                    $validation = WalletValidationService::validateBalance($user);
                    return $this->error(
                        'Insufficient wallet balance to accept bookings.',
                        422,
                        ['wallet_validation' => $validation]
                    );
                }

                $driverProfileId = optional($user->driverProfile)->id;

                if (!$driverProfileId) {
                    return $this->error('Driver profile not found for this user.', 422);
                }

                if (!empty($order->driver_id) && (int) $order->driver_id !== (int) $driverProfileId) {
                    return $this->error('Order already accepted by another driver.', 409);
                }

                $order->driver_id   = $driverProfileId;
                $order->accepted_at = now();
            }

            match ($newStatus) {
                'picked_up'  => $order->picked_up_at  = now(),
                'completed'  => $order->completed_at  = now(),
                'cancelled'  => $order->cancelled_at  = now(),
                default      => null,
            };

            $order->status = $newStatus;
        }

        if (isset($data['payment_status'])) {
            if (!$user->isAdmin() && !$user->isPartnerAdmin()) {
                return $this->error('Only admin/partner_admin can update payment status.', 403);
            }
            $order->payment_status = $data['payment_status'];
        }

        if (array_key_exists('cancel_reason', $data)) {
            $order->cancel_reason = $data['cancel_reason'];
        }

        if (isset($data['actual_distance_m'])) {
            if (!$user->isDriver() && !$user->isAdmin()) {
                return $this->error('Only driver/admin can set actual distance.', 403);
            }

            if ($user->isDriver()) {
                $driverProfileId = optional($user->driverProfile)->id;
                if (!$driverProfileId || (int) $order->driver_id !== (int) $driverProfileId) {
                    return $this->error('You can only update your own assigned order.', 403);
                }
            }

            $order->actual_distance_m = $data['actual_distance_m'];
            $order->loadMissing('service');

            if ($order->service) {
                $serviceCode        = strtoupper((string) ($order->service->code ?? 'LESGO'));
                $distanceKm         = $order->actual_distance_m / 1000;
                $orderValue         = (float) (($order->meta ?? [])['order_value'] ?? 0);
                $order->actual_fare = $this->calculateFare($serviceCode, $distanceKm, $orderValue);
            }
        }

        $order->save();
        $order->load([
            'customer:id,name,email,phone_number',
            'partner:id,name',
            'driverProfile:id,user_id,status,rating',
            'service:id,name,code',
            'pickupAddress:id,address_line1,latitude,longitude',
            'dropoffAddress:id,address_line1,latitude,longitude',
            'payments:id,order_id,amount,status,method,paid_at',
        ]);

        // Bust caches for all parties that can see this order
        CacheService::forget("orders:order:{$order->id}");
        CacheService::forgetByPattern("orders:user:{$order->customer_id}:list:*");
        if ($order->driver_id) {
            CacheService::forgetByPattern("orders:user:*:list:*");
        }

        // Broadcast real-time status update with previous status
        $previousStatus = $order->getOriginal('status') ?? 'pending';
        $this->realtimeService->broadcastOrderStatusUpdate($order, $previousStatus, [
            'updated_by' => $user->id,
            'updated_by_role' => $user->role,
            'timestamp' => now()->toISOString(),
        ]);

        // Queue driver-assigned notification when a driver accepts
        if (isset($data['status']) && $data['status'] === 'accepted') {
            NotifyDriverAssignedJob::dispatch($order)->onQueue('notifications');
        }

        return $this->success($order, 'Order status updated successfully');
    }

    // ── Scoping helpers ──────────────────────────────────────────────────────

    private function scopedOrdersQuery($user)
    {
        $query = Order::query();

        if (!$user)                return $query->whereRaw('1=0');
        if ($user->isAdmin())      return $query;
        if ($user->isCustomer())   return $query->where('customer_id', $user->id);

        if ($user->isDriver()) {
            $id = optional($user->driverProfile)->id;
            return $id ? $query->where('driver_id', $id) : $query->whereRaw('1=0');
        }

        if ($user->isPartnerAdmin()) {
            $id = optional($user->partner)->id;
            return $id ? $query->where('partner_id', $id) : $query->whereRaw('1=0');
        }

        return $query->whereRaw('1=0');
    }

    private function canViewOrder($user, Order $order): bool
    {
        if (!$user)           return false;
        if ($user->isAdmin()) return true;

        if ($user->isCustomer())   return (int) $order->customer_id === (int) $user->id;
        if ($user->isDriver())     return optional($user->driverProfile)->id && (int) $order->driver_id === (int) optional($user->driverProfile)->id;
        if ($user->isPartnerAdmin()) return optional($user->partner)->id && (int) $order->partner_id === (int) optional($user->partner)->id;

        return false;
    }

    private function canUpdateOrder($user, Order $order): bool
    {
        if (!$user)           return false;
        if ($user->isAdmin()) return true;

        return $this->canViewOrder($user, $order) || $user->isDriver();
    }

    private function isStatusChangeAllowed($user, Order $order, string $newStatus): bool
    {
        $new     = strtolower($newStatus);
        $current = strtolower((string) $order->status);

        if (!$user)           return false;
        if ($user->isAdmin()) return true;

        if ($user->isCustomer()) {
            return $new === 'cancelled'
                && !in_array($current, ['completed', 'cancelled'], true)
                && (int) $order->customer_id === (int) $user->id;
        }

        if ($user->isDriver()) {
            $driverId = optional($user->driverProfile)->id;

            if ($new === 'accepted') {
                $canTake   = empty($order->driver_id) || (int) $order->driver_id === (int) $driverId;
                $validFrom = in_array($current, ['pending', 'searching_driver'], true);
                return $driverId && $canTake && $validFrom;
            }

            $owns = $driverId && (int) $order->driver_id === (int) $driverId;
            if (!$owns) return false;

            return match ($new) {
                'picked_up' => $current === 'accepted',
                'completed' => $current === 'picked_up',
                default     => false,
            };
        }

        if ($user->isPartnerAdmin()) {
            $partnerId = optional($user->partner)->id;
            if (!$partnerId || (int) $order->partner_id !== (int) $partnerId) return false;

            return match ($new) {
                'searching_driver' => $current === 'pending',
                'cancelled'        => !in_array($current, ['completed', 'cancelled'], true),
                default            => false,
            };
        }

        return false;
    }

    // ── Fare calculation ─────────────────────────────────────────────────────

    private function buildFareBreakdown(string $serviceCode, float $distanceKm, float $orderValue, float $weightKg, Service $service): array
    {
        $distanceKm  = max(0, min($distanceKm, 30));
        $baseFare    = $service->base_fare    ? (float) $service->base_fare    : 40.0;
        $perKmRate   = $service->per_km_rate  ? (float) $service->per_km_rate  : ($serviceCode === 'LESGO' ? 9.5 : 10.0);
        $minimumFare = $service->minimum_fare ? (float) $service->minimum_fare : $baseFare;
        $firstKm     = 3.0;

        $distanceFare = $distanceKm > $firstKm ? round(($distanceKm - $firstKm) * $perKmRate, 2) : 0.0;

        $serviceFee = 0.0;
        if (in_array($serviceCode, ['LESBUY', 'LESEAT'], true)) {
            $serviceFee = match (true) {
                $orderValue <= 500  => 15.0,
                $orderValue <= 1000 => 30.0,
                default             => 45.0,
            };
        }

        $weightSurcharge = $weightKg > 5 ? round(($weightKg - 5) * 10, 2) : 0.0;
        $subtotal        = $baseFare + $distanceFare + $serviceFee + $weightSurcharge;
        $total           = round(max($subtotal, $minimumFare), 2);

        return [
            'base_fare'        => round($baseFare, 2),
            'distance_fare'    => $distanceFare,
            'service_fee'      => $serviceFee,
            'weight_surcharge' => $weightSurcharge,
            'subtotal'         => round($subtotal, 2),
            'total'            => $total,
            'currency'         => 'PHP',
        ];
    }

    private function calculateFare(string $serviceCode, float $distanceKm, float $orderValue = 0.0): float
    {
        $serviceCode = strtoupper($serviceCode);
        $distanceKm  = max(0, min($distanceKm, 30));

        if ($distanceKm <= 0) return 0.0;

        $baseFare = 40.0;
        $firstKm  = 3.0;

        $fare = $distanceKm <= $firstKm
            ? $baseFare
            : $baseFare + (($distanceKm - $firstKm) * ($serviceCode === 'LESGO' ? 9.5 : 10.0));

        if (in_array($serviceCode, ['LESBUY', 'LESEAT'], true)) {
            $fare += match (true) {
                $orderValue <= 500  => 15,
                $orderValue <= 1000 => 30,
                default             => 45,
            };
        }

        return round($fare, 2);
    }
    
    /**
     * Determine if order should use auto-assignment
     */
    private function shouldAutoAssignDriver(Order $order): bool
    {
        // Check if auto-assignment is enabled globally
        $autoAssignEnabled = \App\Models\SecuritySetting::getValue('driver.auto_assignment_enabled', true);
        
        if (!$autoAssignEnabled) {
            return false;
        }
        
        // Don't auto-assign scheduled orders (assign closer to scheduled time)
        if ($order->scheduled_at && $order->scheduled_at->isFuture()) {
            return false;
        }
        
        // Don't auto-assign if customer specifically requested manual assignment
        $meta = $order->meta ?? [];
        if (isset($meta['manual_assignment']) && $meta['manual_assignment']) {
            return false;
        }
        
        return true;
    }
}
