<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/distance/calculate",
     *     summary="Calculate distance between two coordinates",
     *     tags={"Distance"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="pickup_lat", in="query", required=true, @OA\Schema(type="number", format="float", example=14.5995)),
     *     @OA\Parameter(name="pickup_lng", in="query", required=true, @OA\Schema(type="number", format="float", example=120.9842)),
     *     @OA\Parameter(name="dropoff_lat", in="query", required=true, @OA\Schema(type="number", format="float", example=14.6090)),
     *     @OA\Parameter(name="dropoff_lng", in="query", required=true, @OA\Schema(type="number", format="float", example=121.0000)),
     *     @OA\Response(response=200, description="Distance result",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="distance_m", type="integer", example=5200),
     *                 @OA\Property(property="distance_km", type="number", format="float", example=5.2)
     *             )
     *         )
     *     )
     * )
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'pickup_lat'  => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'  => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $distance = $this->haversine(
            $request->pickup_lat,
            $request->pickup_lng,
            $request->dropoff_lat,
            $request->dropoff_lng
        );

        return $this->success([
            'distance_m'  => round($distance),
            'distance_km' => round($distance / 1000, 2),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/distance/overall",
     *     summary="Get total completed distance across all orders",
     *     tags={"Distance"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Overall distance stats",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_distance_m", type="integer"),
     *                 @OA\Property(property="total_distance_km", type="number", format="float"),
     *                 @OA\Property(property="count_orders", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function overall(Request $request): JsonResponse
    {
        $totalDistanceM = Order::where('status', 'completed')->sum('actual_distance_m');

        return $this->success([
            'total_distance_m'  => (int) $totalDistanceM,
            'total_distance_km' => round($totalDistanceM / 1000, 2),
            'count_orders'      => Order::where('status', 'completed')->count(),
        ]);
    }

    private function haversine(float $latFrom, float $lngFrom, float $latTo, float $lngTo, float $earthRadius = 6371000): float
    {
        $latFrom = deg2rad($latFrom);
        $lngFrom = deg2rad($lngFrom);
        $latTo   = deg2rad($latTo);
        $lngTo   = deg2rad($lngTo);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }
}
