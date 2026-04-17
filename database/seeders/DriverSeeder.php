<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DriverProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        // Create test driver user
        $driver = User::firstOrCreate(
            ['email' => 'driver@lesgo.test'],
            [
                'name'              => 'Test Driver',
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
                'role'              => 'driver',
                'phone_number'      => '+639171234567',
            ]
        );

        // Create driver profile if not exists
        DriverProfile::firstOrCreate(
            ['user_id' => $driver->id],
            [
                'status'         => 'active',
                'rating'         => 4.8,
                'total_trips'    => 0,
                'license_number' => 'N01-23-000001',
                'last_latitude'  => 18.3553,  // Claveria, Cagayan
                'last_longitude' => 121.0803,
            ]
        );

        $this->command->info("Driver seeded: driver@lesgo.test / password");
    }
}
