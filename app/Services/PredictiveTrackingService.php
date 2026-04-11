<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DriverLocation;
use App\Models\OrderTrackingEvent;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PredictiveTrackingService
{
    /**
     * Calculate predictive ETA based on current conditions
     */
    public function calculatePredictiveETA(Order $order): array
    {
        if (!$order->driver_id) {
            return $this->getEstimatedETA($order);
        }
        
        $driverLocation = $this->getCurrentDriverLocation($order);
        if (!$driverLocation) {
            return $this->getEstimatedETA($order);
        }
        
        $eta = $this->calculateRealTimeETA($order, $driverLocation);
        
        return [
            'estimated_arrival' => $eta['arrival_time'],
            'estimated_minutes' => $eta['minutes'],
            'confidence_level' => $eta['confidence'],
            'factors' => $eta['factors'],
            'last_updated' => now()->toISOString()
        ];
    }
    
    /**
     * Get real-time ETA based on driver location and traffic
     */
    private function calculateRealTimeETA(Order $order, DriverLocation $driverLocation): array
    {
        $factors = [];
        
        // Determine destination based on order status
        $destination = $this->getDestinationCoordinates($order);
        
        // Calculate base travel time
        $distance = $this->calculateDistance(
            $driverLocation->latitude,
            $driverLocation->longitude,
            $destination['lat'],
            $destination['lng']
        );
        
        // Base time: 30 km/h average speed in city
        $baseMinutes = ($distance / 30) * 60;
        $factors['base_travel_time'] = round($baseMinutes, 1);
        
        // Traffic adjustment
        $trafficMultiplier = $this->getTrafficMultiplier($driverLocation, $destination);
        $factors['traffic_delay'] = round(($trafficMultiplier - 1) * $baseMinutes, 1);
        
        // Time of day adjustment
        $timeMultiplier = $this->getTimeOfDayMultiplier();
        $factors['time_of_day_factor'] = $timeMultiplier;
        
        // Driver behavior adjustment (based on historical data)
        $driverMultiplier = $this->getDriverSpeedMultiplier($order->driverProfile);
        $factors['driver_efficiency'] = $driverMultiplier;
        
        // Weather adjustment
        $weatherMultiplier = $this->getWeatherMultiplier($destination);
        $factors['weather_impact'] = $weatherMultiplier;
        
        // Calculate final ETA
        $totalMinutes = $baseMinutes * $trafficMultiplier * $timeMultiplier * $driverMultiplier * $weatherMultiplier;
        
        // Add buffer for order status
        if ($order->status === 'accepted' && !$order->picked_up_at) {
            $totalMinutes += 5; // 5 minutes to reach pickup
            $factors['pickup_buffer'] = 5;
        }
        
        $confidence = $this->calculateConfidenceLevel($factors, $distance);
        
        return [
            'minutes' => round($totalMinutes),
            'arrival_time' => now()->addMinutes($totalMinutes),
            'confidence' => $confidence,
            'factors' => $factors
        ];
    }
    
    /**
     * Get destination coordinates based on order status
     */
    private function getDestinationCoordinates(Order $order): array
    {
        if ($order->status === 'accepted' && !$order->picked_up_at) {
            // Driver heading to pickup
            return [
                'lat' => $order->pickup_lat,
                'lng' => $order->pickup_lng
            ];
        } else {
            // Driver heading to dropoff
            return [
                'lat' => $order->dropoff_lat,
                'lng' => $order->dropoff_lng
            ];
        }
    }
    
    /**
     * Get current driver location
     */
    private function getCurrentDriverLocation(Order $order): ?DriverLocation
    {
        return DriverLocation::where('driver_id', $order->driver_id)
            ->where('updated_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();
    }
    
    /**
     * Calculate traffic multiplier based on current conditions
     */
    private function getTrafficMultiplier(DriverLocation $from, array $to): float
    {
        $cacheKey = "traffic_multiplier:" . md5("{$from->latitude},{$from->longitude},{$to['lat']},{$to['lng']}");
        
        return Cache::remember($cacheKey, 300, function () use ($from, $to) {
            // In a real implementation, this would call Google Maps Traffic API
            // For now, simulate based on time and location
            
            $hour = now()->hour;
            $dayOfWeek = now()->dayOfWeek;
            
            // Rush hour traffic
            if (($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19)) {
                return 1.5; // 50% longer during rush hour
            }
            
            // Lunch hour traffic
            if ($hour >= 11 && $hour <= 13) {
                return 1.2; // 20% longer during lunch
            }
            
            // Weekend traffic
            if (in_array($dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                return 0.9; // 10% faster on weekends
            }
            
            return 1.0; // Normal traffic
        });
    }
    
    /**
     * Get time of day multiplier
     */
    private function getTimeOfDayMultiplier(): float
    {
        $hour = now()->hour;
        
        // Late night/early morning - faster travel
        if ($hour >= 22 || $hour <= 5) {
            return 0.8;
        }
        
        // Peak hours - slower travel
        if (($hour >= 7 && $hour <= 9) || ($hour >= 17 && $hour <= 19)) {
            return 1.3;
        }
        
        return 1.0;
    }
    
    /**
     * Get driver speed multiplier based on historical performance
     */
    private function getDriverSpeedMultiplier($driverProfile): float
    {
        if (!$driverProfile) {
            return 1.0;
        }
        
        $cacheKey = "driver_speed_multiplier:{$driverProfile->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($driverProfile) {
            // Calculate average delivery time vs estimated time
            $recentOrders = Order::where('driver_id', $driverProfile->id)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(30))
                ->whereNotNull('estimated_fare')
                ->get();
            
            if ($recentOrders->count() < 5) {
                return 1.0; // Not enough data
            }
            
            $totalRatio = 0;
            $count = 0;
            
            foreach ($recentOrders as $order) {
                if ($order->accepted_at && $order->completed_at) {
                    $actualMinutes = $order->accepted_at->diffInMinutes($order->completed_at);
                    $estimatedMinutes = ($order->estimated_distance_m / 1000 / 30) * 60; // 30 km/h
                    
                    if ($estimatedMinutes > 0) {
                        $totalRatio += $actualMinutes / $estimatedMinutes;
                        $count++;
                    }
                }
            }
            
            if ($count === 0) {
                return 1.0;
            }
            
            $averageRatio = $totalRatio / $count;
            
            // Cap the multiplier between 0.7 and 1.5
            return max(0.7, min(1.5, $averageRatio));
        });
    }
    
    /**
     * Get weather impact multiplier
     */
    private function getWeatherMultiplier(array $location): float
    {
        // In a real implementation, this would call a weather API
        // For now, simulate based on time patterns
        
        $hour = now()->hour;
        $month = now()->month;
        
        // Simulate rainy season in Philippines (June-November)
        if ($month >= 6 && $month <= 11) {
            // Higher chance of rain in afternoon
            if ($hour >= 14 && $hour <= 18) {
                return 1.3; // 30% slower in rain
            }
        }
        
        return 1.0;
    }
    
    /**
     * Calculate confidence level of ETA prediction
     */
    private function calculateConfidenceLevel(array $factors, float $distance): string
    {
        $confidence = 100;
        
        // Reduce confidence for longer distances
        if ($distance > 10) {
            $confidence -= 20;
        } elseif ($distance > 5) {
            $confidence -= 10;
        }
        
        // Reduce confidence during peak hours
        if (isset($factors['traffic_delay']) && $factors['traffic_delay'] > 10) {
            $confidence -= 15;
        }
        
        // Reduce confidence in bad weather
        if (isset($factors['weather_impact']) && $factors['weather_impact'] > 1.2) {
            $confidence -= 10;
        }
        
        if ($confidence >= 85) {
            return 'high';
        } elseif ($confidence >= 70) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Calculate distance between two points
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Get estimated ETA for orders without assigned driver
     */
    private function getEstimatedETA(Order $order): array
    {
        // Base estimation: 5 minutes to assign driver + travel time
        $distance = $this->calculateDistance(
            $order->pickup_lat,
            $order->pickup_lng,
            $order->dropoff_lat,
            $order->dropoff_lng
        );
        
        $travelMinutes = ($distance / 25) * 60; // 25 km/h average city speed
        $totalMinutes = 5 + $travelMinutes; // 5 minutes to assign driver
        
        return [
            'estimated_arrival' => now()->addMinutes($totalMinutes),
            'estimated_minutes' => round($totalMinutes),
            'confidence_level' => 'low',
            'factors' => [
                'status' => 'awaiting_driver_assignment',
                'estimated_assignment_time' => 5,
                'estimated_travel_time' => round($travelMinutes, 1)
            ],
            'last_updated' => now()->toISOString()
        ];
    }
}