<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'              => 'Test User',
                'email_verified_at' => now(),
                'password'          => \Illuminate\Support\Facades\Hash::make('password'),
                'remember_token'    => \Illuminate\Support\Str::random(10),
                'role'              => 'customer',
                'phone_number'      => '+639154189962',
            ]
        );

        // Admin user for management tasks
        User::firstOrCreate(
            ['email' => 'admin@lesgo.test'],
            [
                'name'              => 'Admin User',
                'email_verified_at' => now(),
                'password'          => \Illuminate\Support\Facades\Hash::make('admin123'),
                'remember_token'    => \Illuminate\Support\Str::random(10),
                'role'              => 'admin',
                'phone_number'      => '+639000000001',
            ]
        );

        $this->call([
            ServiceSeeder::class,
            RestaurantSeeder::class,
            LesbuySeeder::class,
            DriverSeeder::class,
        ]);
    }
}
