<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverProfileFactory extends Factory
{
    protected $model = \App\Models\DriverProfile::class;

    public function definition(): array
    {
        return [
            'user_id'             => User::factory()->driver(),
            'status'              => 'approved',
            'is_available'        => true,
            'package_tier'        => 'standard',
            'rating'              => fake()->randomFloat(1, 4, 5),
            'total_trips'         => fake()->numberBetween(0, 500),
            'license_number'      => strtoupper(fake()->bothify('??-######')),
            'license_expiry_date' => now()->addYears(2)->toDateString(),
            'vehicle_type'        => fake()->randomElement(['motorcycle', 'car', 'van']),
            'plate_number'        => strtoupper(fake()->bothify('??? ####')),
        ];
    }
}
