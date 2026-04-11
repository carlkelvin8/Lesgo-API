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
            'service_id' => 'required|integer',
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'dropoff_lat' => 'required|numeric',
            'dropoff_lng' => 'required|numeric',
            'estimated_distance_m' => 'required|integer|min:0'
        ]);

        // Basic fare calculation
        $distanceKm = $validated['estimated_distance_m'] / 1000;
        $baseFare = 40.0;
        $perKmRate = 9.5;
        $fare = $baseFare + ($distanceKm * $perKmRate);

        return $this->success([
            'estimated_fare' => round($fare, 2),
            'distance_km' => round($distanceKm, 2),
            'base_fare' => $baseFare,
            'per_km_rate' => $perKmRate,
            'currency' => 'PHP'
        ], 'Fare estimated successfully');
    }
}