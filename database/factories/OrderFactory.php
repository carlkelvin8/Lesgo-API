<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'          => User::factory(),
            'service_id'           => Service::factory(),
            'status'               => 'pending',
            'payment_method'       => 'cash',
            'payment_status'       => 'pending',
            'estimated_distance_m' => fake()->numberBetween(1000, 10000),
            'estimated_fare'       => fake()->randomFloat(2, 40, 300),
            'meta'                 => [
                'pickup'  => ['address' => '123 Rizal St', 'lat' => 14.5995, 'lng' => 120.9842],
                'dropoff' => ['address' => '456 Mabini Ave', 'lat' => 14.6090, 'lng' => 121.0000],
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'completed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
