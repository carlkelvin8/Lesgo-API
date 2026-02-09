<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DistanceController extends Controller
{
    /**
     * Calculate distance between two points (Haversine Formula)
     * GET /api/v1/distance/calculate
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'pickup_lat'   => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'   => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat'  => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng'  => ['required', 'numeric', 'between:-180,180'],
        ]);

        $distance = $this->haversineGreatCircleDistance(
            $request->pickup_lat,
            $request->pickup_lng,
            $request->dropoff_lat,
            $request->dropoff_lng
        );

        return response()->json([
            'distance_m'  => round($distance),
            'distance_km' => round($distance / 1000, 2),
        ]);
    }

    /**
     * Get overall distance statistics
     * GET /api/v1/distance/overall
     */
    public function overall(Request $request)
    {
        // Example: Total distance of all completed orders
        $totalDistanceM = Order::where('status', 'completed')
            ->sum('actual_distance_m'); // Or estimated_distance_m if actual is null

        // If you want to include estimated for completed orders where actual is missing:
        // This logic depends on how strictly you populate actual_distance_m

        return response()->json([
            'total_distance_m'  => (int) $totalDistanceM,
            'total_distance_km' => round($totalDistanceM / 1000, 2),
            'count_orders'      => Order::where('status', 'completed')->count(),
        ]);
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Haversine formula.
     * @return float Distance in meters
     */
    private function haversineGreatCircleDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}
