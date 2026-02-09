<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_calculate_distance()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        // Coordinates for roughly 1km or known distance
        // Lat 14.5800, Lng 121.0600 (Ortigas)
        // Lat 14.5900, Lng 121.0700 (Nearby)
        $response = $this->actingAs($user)->getJson('/api/v1/distance/calculate?pickup_lat=14.5800&pickup_lng=121.0600&dropoff_lat=14.5900&dropoff_lng=121.0700');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure(['distance_m', 'distance_km']);
        
        $this->assertGreaterThan(0, $response->json('distance_m'));
    }

    public function test_overall_distance()
    {
        // Arrange
        $user = User::factory()->create();
        $service = Service::create([
            'code' => 'TEST',
            'name' => 'Test Service',
            'base_fare' => 10,
            'per_km_rate' => 10,
            'per_minute_rate' => 1,
            'minimum_fare' => 10,
            'is_active' => true,
        ]);
        
        // We need to create orders directly in DB to simulate history
        Order::forceCreate([
            'customer_id' => $user->id,
            'status' => 'completed',
            'actual_distance_m' => 5000,
            'service_id' => $service->id,
            'estimated_distance_m' => 5000,
            'estimated_fare' => 100,
            'actual_fare' => 100,
        ]);

        Order::forceCreate([
            'customer_id' => $user->id,
            'status' => 'completed',
            'actual_distance_m' => 3000,
            'service_id' => $service->id,
            'estimated_distance_m' => 3000,
            'estimated_fare' => 100,
            'actual_fare' => 100,
        ]);

        // Act
        $response = $this->actingAs($user)->getJson('/api/v1/distance/overall');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'total_distance_m' => 8000,
                'count_orders' => 2
            ]);
    }
}
