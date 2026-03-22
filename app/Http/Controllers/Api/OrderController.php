<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Address;
use App\Models\Order;
use App\Models\Service;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(FilterOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $cacheKey = "orders:user:{$user->id}:list:" . md5(serialize($validated));

        $paginator = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($validated, $user) {
            $query = $this->scopedOrdersQuery($user)->with([
                'customer', 'partner', 'driverProfile',
                'service', 'pickupAddress', 'dropoffAddress', 'lesbuyItems',
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

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data    = $request->validated();
        $user    = $request->user();
        $service = Service::findOrFail($data['service_id']);

        $serviceCode   = strtoupper((string) ($service->code ?? 'LESGO'));
        $distanceKm    = $data['estimated_distance_m'] / 1000;
        $meta          = $data['meta'] ?? [];
        $orderValue    = (float) ($meta['order_value'] ?? 0);
        $estimatedFare = $this->calculateFare($serviceCode, $distanceKm, $orderValue);
        $saveAddresses = (bool) ($data['save_addresses'] ?? false);

        $order = DB::transaction(function () use ($user, $data, $estimatedFare, $meta, $saveAddresses) {
            $pickupAddressId  = null;
            $dropoffAddressId = null;

            if ($saveAddresses) {
                $pickupAddress = Address::create([
                    'user_id'       => $user->id,
                    'label'         => $data['pickup_label'] ?? 'Pickup',
                    'contact_name'  => $data['contact_name'] ?? $user->name,
                    'contact_phone' => $data['contact_phone'] ?? $user->phone_number,
                    'address_line1' => $data['pickup']['address'],
                    'country'       => 'PH',
                    'latitude'      => $data['pickup']['lat'],
                    'longitude'     => $data['pickup']['lng'],
                    'is_default'    => false,
                ]);

                $dropoffAddress = Address::create([
                    'user_id'       => $user->id,
                    'label'         => $data['dropoff_label'] ?? 'Dropoff',
                    'contact_name'  => $data['contact_name'] ?? $user->name,
                    'contact_phone' => $data['contact_phone'] ?? $user->phone_number,
                    'address_line1' => $data['dropoff']['address'],
                    'country'       => 'PH',
                    'latitude'      => $data['dropoff']['lat'],
                    'longitude'     => $data['dropoff']['lng'],
                    'is_default'    => false,
                ]);

                $pickupAddressId  = $pickupAddress->id;
                $dropoffAddressId = $dropoffAddress->id;
            }

            $metaMerged = array_merge($meta, [
                'pickup'         => ['address' => $data['pickup']['address'], 'lat' => (float) $data['pickup']['lat'], 'lng' => (float) $data['pickup']['lng']],
                'dropoff'        => ['address' => $data['dropoff']['address'], 'lat' => (float) $data['dropoff']['lat'], 'lng' => (float) $data['dropoff']['lng']],
                'save_addresses' => $saveAddresses,
            ]);

            return Order::create([
                'customer_id'          => $user->id,
                'partner_id'           => null,
                'driver_id'            => null,
                'service_id'           => $data['service_id'],
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

        $order->load(['customer', 'partner', 'driverProfile', 'service', 'pickupAddress', 'dropoffAddress', 'payments', 'lesbuyItems']);

        // Bust the order list cache for this customer
        CacheService::forgetByPattern("orders:user:{$user->id}:list:*");

        return $this->created($order, 'Order created successfully');
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if (!$this->canViewOrder($request->user(), $order)) {
            return $this->error('Forbidden', 403);
        }

        $cacheKey = "orders:order:{$order->id}";

        $order = CacheService::remember($cacheKey, CacheService::CACHE_SHORT, function () use ($order) {
            $order->load(['customer', 'partner', 'driverProfile', 'service', 'pickupAddress', 'dropoffAddress', 'payments', 'lesbuyItems']);
            return $order;
        });

        return $this->success($order);
    }

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
        $order->load(['customer', 'partner', 'driverProfile', 'service', 'pickupAddress', 'dropoffAddress', 'payments']);

        // Bust caches for all parties that can see this order
        CacheService::forget("orders:order:{$order->id}");
        CacheService::forgetByPattern("orders:user:{$order->customer_id}:list:*");
        if ($order->driver_id) {
            CacheService::forgetByPattern("orders:user:*:list:*");
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
}
