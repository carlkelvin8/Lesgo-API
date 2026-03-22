<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partner_id'      => null,
            'code'            => fake()->randomElement(['LESGO', 'LESBUY', 'LESEAT']),
            'name'            => fake()->words(2, true),
            'description'     => fake()->sentence(),
            'base_fare'       => 40.00,
            'per_km_rate'     => 9.50,
            'per_minute_rate' => 0.50,
            'minimum_fare'    => 40.00,
            'is_active'       => true,
        ];
    }
}
