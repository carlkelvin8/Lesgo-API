<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderEstimateController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/orders/estimate",
     *     summary="Estimate order fare",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Fare estimate")
     * )
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'pickup' => 'required|array',
            'pickup.lat' => 'required|numeric|between:-90,90',
            'pickup.lng' => 'required|numeric|between:-180,180',
            'dropoff' => 'required|array',
            'dropoff.lat' => 'required|numeric|between:-90,90',
            'dropoff.lng' => 'required|numeric|between:-180,180',
            'estimated_distance_m' => 'nullable|integer|min:0',
            'items' => 'nullable|array',
            'order_value' => 'nullable|numeric|min:0',
            'estimated_weight_kg' => 'nullable|numeric|min:0',
        ]);

        // Calculate distance if not provided
        if (empty($validated['estimated_distance_m'])) {
            $distanceM = $this->calculateHaversineDistance(
                $validated['pickup']['lat'],
                $validated['pickup']['lng'],
                $validated['dropoff']['lat'],
                $validated['dropoff']['lng']
            );
        } else {
            $distanceM = $validated['estimated_distance_m'];
        }

        $service = \App\Models\Service::findOrFail($validated['service_id']);
        $distanceKm = $distanceM / 1000;
        $orderValue = (float) ($validated['order_value'] ?? 0);
        $weightKg = (float) ($validated['estimated_weight_kg'] ?? 0);
        $serviceCode = strtoupper((string) ($service->code ?? 'LESGO'));

        // Build fare breakdown
        $baseFare = $service->base_fare ? (float) $service->base_fare : 40.0;
        $perKmRate = $service->per_km_rate ? (float) $service->per_km_rate : 9.5;
        $minimumFare = $service->minimum_fare ? (float) $service->minimum_fare : $baseFare;
        $firstKm = 3.0;

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
        $subtotal = $baseFare + $distanceFare + $serviceFee + $weightSurcharge;
        $total = round(max($subtotal, $minimumFare), 2);

        // Estimated duration (assuming 30 km/h average speed)
        $avgSpeedKmh = 30;
        $estimatedDuration = $distanceKm > 0 ? round(($distanceKm / $avgSpeedKmh) * 60, 2) : 0;

        return $this->success([
            'distance_m' => round($distanceM),
            'distance_km' => round($distanceKm, 2),
            'estimated_fare' => $total,
            'estimated_duration_minutes' => $estimatedDuration,
            'fare_breakdown' => [
                'base_fare' => round($baseFare, 2),
                'distance_fare' => $distanceFare,
                'service_fee' => $serviceFee,
                'weight_surcharge' => $weightSurcharge,
                'subtotal' => round($subtotal, 2),
                'total' => $total,
                'currency' => 'PHP',
            ],
            'payment_methods' => ['cash', 'gcash', 'maya', 'card', 'wallet'],
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'code' => $service->code,
            ],
        ], 'Fare estimated successfully');
    }

    /**
     * Calculate distance between two points using Haversine formula.
     */
    private function calculateHaversineDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}