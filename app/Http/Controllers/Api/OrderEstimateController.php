<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderEstimateController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/orders/estimate",
     *     summary="Estimate fare before booking",
     *     description="Computes distance and fare based on pickup/dropoff coordinates, service type, item details, and weight. Call this BEFORE POST /orders so the user sees the price first.",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"service_id","pickup","dropoff"},
     *             @OA\Property(property="service_id", type="integer", example=1),
     *             @OA\Property(property="pickup", type="object", required={"lat","lng"},
     *                 @OA\Property(property="lat", type="number", example=14.5995),
     *                 @OA\Property(property="lng", type="number", example=120.9842)
     *             ),
     *             @OA\Property(property="dropoff", type="object", required={"lat","lng"},
     *                 @OA\Property(property="lat", type="number", example=14.5547),
     *                 @OA\Property(property="lng", type="number", example=121.0244)
     *             ),
     *             @OA\Property(property="item_description", type="string", example="Books, Clothes, Electronics"),
     *             @OA\Property(property="estimated_weight_kg", type="number", example=5),
     *             @OA\Property(property="order_value", type="number", example=500, description="Total value of items (for LesBuy/LesEat service fee)"),
     *             @OA\Property(property="payment_method", type="string", enum={"cash","gcash","maya","card","wallet"}, example="cash")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Fare estimate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="service", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="LesGo"),
     *                     @OA\Property(property="code", type="string", example="LESGO")
     *                 ),
     *                 @OA\Property(property="distance_m", type="integer", example=5200),
     *                 @OA\Property(property="distance_km", type="number", example=5.2),
     *                 @OA\Property(property="estimated_fare", type="number", example=71.90),
     *                 @OA\Property(property="fare_breakdown", type="object",
     *                     @OA\Property(property="base_fare", type="number", example=40.00),
     *                     @OA\Property(property="distance_fare", type="number", example=21.90),
     *                     @OA\Property(property="service_fee", type="number", example=0),
     *                     @OA\Property(property="weight_surcharge", type="number", example=10.00),
     *                     @OA\Property(property="total", type="number", example=71.90)
     *                 ),
     *                 @OA\Property(property="estimated_duration_minutes", type="integer", example=18),
     *                 @OA\Property(property="payment_methods", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Service not found")
     * )
     */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id'            => ['required', 'integer', 'exists:services,id'],
            'pickup'                => ['required', 'array'],
            'pickup.lat'            => ['required', 'numeric', 'between:-90,90'],
            'pickup.lng'            => ['required', 'numeric', 'between:-180,180'],
            'dropoff'               => ['required', 'array'],
            'dropoff.lat'           => ['required', 'numeric', 'between:-90,90'],
            'dropoff.lng'           => ['required', 'numeric', 'between:-180,180'],
            'item_description'      => ['nullable', 'string', 'max:500'],
            'estimated_weight_kg'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'order_value'           => ['nullable', 'numeric', 'min:0'],
            'payment_method'        => ['nullable', 'string', 'in:cash,gcash,maya,card,wallet'],
        ]);

        $service = Service::findOrFail($data['service_id']);

        // Calculate distance using Haversine formula
        $distanceM  = $this->haversine(
            $data['pickup']['lat'],
            $data['pickup']['lng'],
            $data['dropoff']['lat'],
            $data['dropoff']['lng']
        );
        $distanceKm = $distanceM / 1000;

        // Fare breakdown
        $orderValue     = (float) ($data['order_value'] ?? 0);
        $weightKg       = (float) ($data['estimated_weight_kg'] ?? 0);
        $serviceCode    = strtoupper((string) ($service->code ?? 'LESGO'));
        $breakdown      = $this->calculateFareBreakdown($serviceCode, $distanceKm, $orderValue, $weightKg, $service);

        // Estimated duration: ~3 min/km in city traffic + 5 min buffer
        $estimatedMinutes = max(5, (int) round($distanceKm * 3 + 5));

        return $this->success([
            'service' => [
                'id'          => $service->id,
                'name'        => $service->name,
                'code'        => $service->code,
                'icon_url'    => $service->icon_url,
                'description' => $service->description,
            ],
            'distance_m'               => (int) round($distanceM),
            'distance_km'              => round($distanceKm, 2),
            'estimated_fare'           => $breakdown['total'],
            'fare_breakdown'           => $breakdown,
            'estimated_duration_minutes' => $estimatedMinutes,
            'payment_methods'          => ['cash', 'gcash', 'maya', 'card', 'wallet'],
        ]);
    }

    /**
     * Calculate detailed fare breakdown.
     */
    private function calculateFareBreakdown(
        string $serviceCode,
        float $distanceKm,
        float $orderValue,
        float $weightKg,
        Service $service
    ): array {
        $distanceKm = max(0, min($distanceKm, 30));

        // Use service's configured rates if available, else defaults
        $baseFare    = $service->base_fare    ? (float) $service->base_fare    : 40.0;
        $perKmRate   = $service->per_km_rate  ? (float) $service->per_km_rate  : ($serviceCode === 'LESGO' ? 9.5 : 10.0);
        $minimumFare = $service->minimum_fare ? (float) $service->minimum_fare : $baseFare;
        $firstKm     = 3.0;

        // Base + distance fare
        if ($distanceKm <= $firstKm) {
            $distanceFare = 0.0;
            $computedBase = $baseFare;
        } else {
            $distanceFare = round(($distanceKm - $firstKm) * $perKmRate, 2);
            $computedBase = $baseFare;
        }

        // Service fee for LesBuy / LesEat based on order value
        $serviceFee = 0.0;
        if (in_array($serviceCode, ['LESBUY', 'LESEAT'], true)) {
            $serviceFee = match (true) {
                $orderValue <= 500  => 15.0,
                $orderValue <= 1000 => 30.0,
                default             => 45.0,
            };
        }

        // Weight surcharge: free up to 5kg, +₱10 per kg after
        $weightSurcharge = 0.0;
        if ($weightKg > 5) {
            $weightSurcharge = round(($weightKg - 5) * 10, 2);
        }

        $subtotal = $computedBase + $distanceFare + $serviceFee + $weightSurcharge;
        $total    = round(max($subtotal, $minimumFare), 2);

        return [
            'base_fare'        => round($computedBase, 2),
            'distance_fare'    => $distanceFare,
            'service_fee'      => $serviceFee,
            'weight_surcharge' => $weightSurcharge,
            'subtotal'         => round($subtotal, 2),
            'total'            => $total,
            'currency'         => 'PHP',
        ];
    }

    /**
     * Haversine formula — distance in meters between two GPS points.
     */
    private function haversine(float $latFrom, float $lngFrom, float $latTo, float $lngTo): float
    {
        $earthRadius = 6371000;
        $latFrom     = deg2rad($latFrom);
        $lngFrom     = deg2rad($lngFrom);
        $latTo       = deg2rad($latTo);
        $lngTo       = deg2rad($lngTo);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }
}
