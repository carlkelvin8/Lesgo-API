<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'id'           => 1,
                'code'         => 'LESGO',
                'name'         => 'LesGo',
                'description'  => 'Ride-hailing service',
                'category'     => 'ride',
                'base_fare'    => 40.00,
                'per_km_rate'  => 9.50,
                'minimum_fare' => 40.00,
                'is_active'    => true,
            ],
            [
                'id'           => 2,
                'code'         => 'LESRIDE',
                'name'         => 'LesRide',
                'description'  => 'Motorcycle ride service',
                'category'     => 'ride',
                'base_fare'    => 30.00,
                'per_km_rate'  => 8.00,
                'minimum_fare' => 30.00,
                'is_active'    => true,
            ],
            [
                'id'           => 3,
                'code'         => 'LESEAT',
                'name'         => 'LesEat',
                'description'  => 'Food delivery service',
                'category'     => 'food',
                'base_fare'    => 40.00,
                'per_km_rate'  => 10.00,
                'minimum_fare' => 40.00,
                'is_active'    => true,
            ],
            [
                'id'           => 4,
                'code'         => 'LESBUY',
                'name'         => 'LesBuy',
                'description'  => 'Grocery and shopping delivery',
                'category'     => 'shopping',
                'base_fare'    => 40.00,
                'per_km_rate'  => 10.00,
                'minimum_fare' => 40.00,
                'is_active'    => true,
            ],
            [
                'id'           => 5,
                'code'         => 'LESTRANSPORT',
                'name'         => 'LesTransport',
                'description'  => 'Cargo and transport service',
                'category'     => 'transport',
                'base_fare'    => 60.00,
                'per_km_rate'  => 12.00,
                'minimum_fare' => 60.00,
                'is_active'    => true,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['id' => $service['id']], $service);
        }
    }
}
