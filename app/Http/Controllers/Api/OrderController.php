<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * GET /api/v1/orders
     * Secure scoping:
     * - customer: only own orders
     * - driver: only assigned orders (driver_id = driver's driverProfile.id)
     * - partner_admin: only partner orders (partner_id = user's partner.id)
     * - admin: all orders
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                'pending',
                'searching_driver',
                'accepted',
                'picked_up',
                'completed',
                'cancelled',
            ])],
            'payment_status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
            'service_id'     => ['nullable', 'integer', 'exists:services,id'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->scopedOrdersQuery($user)->with([
            'customer',
            'partner',
            'driverProfile',
            'service',
            'pickupAddress',
            'dropoffAddress',
            'lesbuyItems',
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

        return $this->success(
            $query->orderByDesc('id')->paginate($perPage)
        );
    }

    /**
     * POST /api/v1/orders
     *
     * Map-pin style:
     * - pickup/dropoff can be sent as objects (lat/lng/address)
     * - OPTIONAL: if save_addresses=true, we create Address rows and use pickup_address_id/dropoff_address_id
     * - customer_id always comes from authenticated user
     *
     * Body example:
     * {
     *   "service_id": 7,
     *   "estimated_distance_m": 12000,
     *   "pickup": {"address":"SM Megamall","lat":14.58,"lng":121.06},
     *   "dropoff":{"address":"Ortigas","lat":14.59,"lng":121.07},
     *   "save_addresses": true,
     *   "payment_method":"cash",
     *   "meta":{"order_value":750}
     * }
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isCustomer()) {
            return $this->error('Only customers can create orders.', 403);
        }

        $data = $request->validate([
            'service_id'           => ['required', 'integer', 'exists:services,id'],
            'scheduled_at'         => ['nullable', 'date'],
            'estimated_distance_m' => ['required', 'integer', 'min:1', 'max:30000'],
            'payment_method'       => ['nullable', 'string', 'max:50'],
            'meta'                 => ['nullable', 'array'],

            // Map-pin inputs
            'pickup'               => ['required', 'array'],
            'pickup.address'       => ['required', 'string', 'max:255'],
            'pickup.lat'           => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng'           => ['required', 'numeric', 'between:-180,180'],

            'dropoff'              => ['required', 'array'],
            'dropoff.address'      => ['required', 'string', 'max:255'],
            'dropoff.lat'          => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng'          => ['required', 'numeric', 'between:-180,180'],

            // Optional: create Address records from pins
            'save_addresses'       => ['nullable', 'boolean'],
            'pickup_label'         => ['nullable', 'string', 'max:100'],
            'dropoff_label'        => ['nullable', 'string', 'max:100'],
            'contact_name'         => ['nullable', 'string', 'max:255'],
            'contact_phone'        => ['nullable', 'string', 'max:100'],

            // Lesbuy Items
            'items'                     => ['nullable', 'array'],
            'items.*.name'              => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity'          => ['required_with:items', 'integer', 'min:1'],
            'items.*.estimated_price'   => ['nullable', 'numeric', 'min:0'],
            'items.*.is_checklist_item' => ['nullable', 'boolean'],
        ]);

        /** @var Service $service */
        $service = Service::query()->findOrFail($data['service_id']);

        $serviceCode = strtoupper((string) ($service->code ?? 'LESGO'));
        $distanceKm  = $data['estimated_distance_m'] / 1000;

        $meta = $data['meta'] ?? [];
        $orderValue = !empty($meta['order_value']) ? (float) $meta['order_value'] : 0.0;

        $estimatedFare = $this->calculateFare($serviceCode, $distanceKm, $orderValue);

        $saveAddresses = (bool) ($data['save_addresses'] ?? false);

        $order = DB::transaction(function () use ($user, $data, $estimatedFare, $meta, $saveAddresses) {
            $pickupAddressId  = null;
            $dropoffAddressId = null;

            if ($saveAddresses) {
                $pickupAddress = Address::create([
                    'user_id'        => $user->id,
                    'label'          => $data['pickup_label'] ?? 'Pickup',
                    'contact_name'   => $data['contact_name'] ?? $user->name,
                    'contact_phone'  => $data['contact_phone'] ?? $user->phone_number,
                    'address_line1'  => $data['pickup']['address'],
                    'address_line2'  => null,
                    'city'           => null,
                    'region'         => null,
                    'country'        => 'PH',
                    'postal_code'    => null,
                    'latitude'       => $data['pickup']['lat'],
                    'longitude'      => $data['pickup']['lng'],
                    'is_default'     => false,
                ]);

                $dropoffAddress = Address::create([
                    'user_id'        => $user->id,
                    'label'          => $data['dropoff_label'] ?? 'Dropoff',
                    'contact_name'   => $data['contact_name'] ?? $user->name,
                    'contact_phone'  => $data['contact_phone'] ?? $user->phone_number,
                    'address_line1'  => $data['dropoff']['address'],
                    'address_line2'  => null,
                    'city'           => null,
                    'region'         => null,
                    'country'        => 'PH',
                    'postal_code'    => null,
                    'latitude'       => $data['dropoff']['lat'],
                    'longitude'      => $data['dropoff']['lng'],
                    'is_default'     => false,
                ]);

                $pickupAddressId  = $pickupAddress->id;
                $dropoffAddressId = $dropoffAddress->id;
            }

            // Store map-pin snapshot in meta so you don't need DB changes
            $metaMerged = array_merge($meta ?? [], [
                'pickup' => [
                    'address' => $data['pickup']['address'],
                    'lat'     => (float) $data['pickup']['lat'],
                    'lng'     => (float) $data['pickup']['lng'],
                ],
                'dropoff' => [
                    'address' => $data['dropoff']['address'],
                    'lat'     => (float) $data['dropoff']['lat'],
                    'lng'     => (float) $data['dropoff']['lng'],
                ],
                'save_addresses' => $saveAddresses,
            ]);

            return Order::create([
                'customer_id'          => $user->id,
                'partner_id'           => null,
                'driver_id'            => null,
                'service_id'           => $data['service_id'],

                // Optional FK-based addresses
                'pickup_address_id'    => $pickupAddressId,
                'dropoff_address_id'   => $dropoffAddressId,

                'status'               => 'pending',
                'scheduled_at'         => $data['scheduled_at'] ?? null,
                'estimated_distance_m' => $data['estimated_distance_m'],
                'estimated_fare'       => $estimatedFare,
                'payment_method'       => $data['payment_method'] ?? 'cash',
                'payment_status'       => 'pending',
                'meta'                 => $metaMerged ?: null,
            ]);
        });

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $order->lesbuyItems()->create([
                    'name'              => $item['name'],
                    'quantity'          => $item['quantity'],
                    'estimated_price'   => $item['estimated_price'] ?? null,
                    'is_checklist_item' => $item['is_checklist_item'] ?? false,
                    'status'            => 'pending',
                ]);
            }
        }

        $order->load([
            'customer',
            'partner',
            'driverProfile',
            'service',
            'pickupAddress',
            'dropoffAddress',
            'payments',
            'lesbuyItems',
        ]);

        return $this->created($order, 'Order created successfully');
    }

    /**
     * GET /api/v1/orders/{order}
     * Secure scoping: user must be allowed to view the order.
     */
    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        if (!$this->canViewOrder($user, $order)) {
            return $this->error('Forbidden', 403);
        }

        $order->load([
            'customer',
            'partner',
            'driverProfile',
            'service',
            'pickupAddress',
            'dropoffAddress',
            'payments',
            'lesbuyItems',
        ]);

        return $this->success($order);
    }

    /**
     * PATCH /api/v1/orders/{order}/status
     * Automatic driver assignment:
     * - If a driver sets status=accepted, assigns order.driver_id to the driver's driverProfile.id
     * - Prevents double-accept by another driver (409)
     */
    public function updateStatus(Request $request, Order $order)
    {
        $user = $request->user();

        if (!$this->canUpdateOrder($user, $order)) {
            return $this->error('Forbidden', 403);
        }

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                'pending',
                'searching_driver',
                'accepted',
                'picked_up',
                'completed',
                'cancelled',
            ])],

            'payment_status' => ['nullable', 'string', Rule::in([
                'pending',
                'paid',
                'failed',
                'refunded',
            ])],

            'cancel_reason'     => ['nullable', 'string', 'max:255'],
            'actual_distance_m' => ['nullable', 'integer', 'min:1', 'max:30000'],
        ]);

        if (isset($data['status'])) {
            $newStatus = $data['status'];

            if (!$this->isStatusChangeAllowed($user, $order, $newStatus)) {
                return $this->error('Status change not allowed for your role or current order state.', 403);
            }

            if ($newStatus === 'accepted') {
                if (!$user->isDriver()) {
                    return $this->error('Only drivers can accept orders.', 403);
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

            if ($newStatus === 'picked_up') {
                $order->picked_up_at = now();
            } elseif ($newStatus === 'completed') {
                $order->completed_at = now();
            } elseif ($newStatus === 'cancelled') {
                $order->cancelled_at = now();
            }

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
                $serviceCode = strtoupper((string) ($order->service->code ?? 'LESGO'));
                $distanceKm  = $order->actual_distance_m / 1000;

                $meta = $order->meta ?? [];
                $orderValue = !empty($meta['order_value']) ? (float) $meta['order_value'] : 0.0;

                $order->actual_fare = $this->calculateFare($serviceCode, $distanceKm, $orderValue);
            }
        }

        $order->save();

        $order->load([
            'customer',
            'partner',
            'driverProfile',
            'service',
            'pickupAddress',
            'dropoffAddress',
            'payments',
        ]);

        return $this->success($order, 'Order status updated successfully');
    }

    /* =========================
       SECURITY / SCOPING
    ========================= */

    private function scopedOrdersQuery($user)
    {
        $query = Order::query();

        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isCustomer()) {
            return $query->where('customer_id', $user->id);
        }

        if ($user->isDriver()) {
            $driverProfileId = optional($user->driverProfile)->id;
            return $driverProfileId ? $query->where('driver_id', $driverProfileId) : $query->whereRaw('1=0');
        }

        if ($user->isPartnerAdmin()) {
            $partnerId = optional($user->partner)->id;
            return $partnerId ? $query->where('partner_id', $partnerId) : $query->whereRaw('1=0');
        }

        return $query->whereRaw('1=0');
    }

    private function canViewOrder($user, Order $order): bool
    {
        if (!$user) return false;
        if ($user->isAdmin()) return true;

        if ($user->isCustomer()) {
            return (int) $order->customer_id === (int) $user->id;
        }

        if ($user->isDriver()) {
            $driverProfileId = optional($user->driverProfile)->id;
            return $driverProfileId && (int) $order->driver_id === (int) $driverProfileId;
        }

        if ($user->isPartnerAdmin()) {
            $partnerId = optional($user->partner)->id;
            return $partnerId && (int) $order->partner_id === (int) $partnerId;
        }

        return false;
    }

    private function canUpdateOrder($user, Order $order): bool
    {
        if (!$user) return false;
        if ($user->isAdmin()) return true;

        return $this->canViewOrder($user, $order) || $user->isDriver();
    }

    private function isStatusChangeAllowed($user, Order $order, string $newStatus): bool
    {
        $newStatus = strtolower($newStatus);
        $current   = strtolower((string) $order->status);

        if (!$user) return false;

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCustomer()) {
            if ($newStatus === 'cancelled') {
                return !in_array($current, ['completed', 'cancelled'], true)
                    && (int) $order->customer_id === (int) $user->id;
            }
            return false;
        }

        if ($user->isDriver()) {
            $driverProfileId = optional($user->driverProfile)->id;

            if ($newStatus === 'accepted') {
                if (!$driverProfileId) return false;

                $canTake = empty($order->driver_id) || (int) $order->driver_id === (int) $driverProfileId;
                $validFrom = in_array($current, ['pending', 'searching_driver'], true);

                return $canTake && $validFrom;
            }

            $owns = $driverProfileId && (int) $order->driver_id === (int) $driverProfileId;
            if (!$owns) return false;

            if ($newStatus === 'picked_up') {
                return in_array($current, ['accepted'], true);
            }

            if ($newStatus === 'completed') {
                return in_array($current, ['picked_up'], true);
            }

            return false;
        }

        if ($user->isPartnerAdmin()) {
            $partnerId = optional($user->partner)->id;
            if (!$partnerId || (int) $order->partner_id !== (int) $partnerId) return false;

            if ($newStatus === 'searching_driver') {
                return in_array($current, ['pending'], true);
            }

            if ($newStatus === 'cancelled') {
                return !in_array($current, ['completed', 'cancelled'], true);
            }

            return false;
        }

        return false;
    }

    /* =========================
       FARE CALC
    ========================= */

    private function calculateFare(string $serviceCode, float $distanceKm, float $orderValue = 0.0): float
    {
        $serviceCode = strtoupper($serviceCode);

        $distanceKm = max(0, min($distanceKm, 30));
        if ($distanceKm <= 0) return 0.0;

        $baseFare       = 40.0;
        $firstKmCovered = 3.0;
        $perKmLeSGo     = 9.5;
        $perKmOthers    = 10.0;

        if ($distanceKm <= $firstKmCovered) {
            $fare = $baseFare;
        } else {
            $extraKm = $distanceKm - $firstKmCovered;

            if ($serviceCode === 'LESGO') {
                $fare = $baseFare + ($extraKm * $perKmLeSGo);
            } else {
                $fare = $baseFare + ($extraKm * $perKmOthers);
            }
        }

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
