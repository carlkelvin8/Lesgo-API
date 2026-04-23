<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProofImageTestSeeder extends Seeder
{
    /**
     * Seed test orders with proof images for testing
     */
    public function run(): void
    {
        // Find a driver with a profile (use Driver 1)
        $driver = User::find(6);
        
        if (!$driver) {
            $this->command->error('Driver not found!');
            return;
        }

        $this->command->info("Using driver: {$driver->name} (ID: {$driver->id})");

        // Find a customer
        $customer = User::where('role', 'customer')->first();
        
        if (!$customer) {
            $this->command->error('No customer found!');
            return;
        }

        $this->command->info('Creating test orders with proof images...');

        // Create 3 completed orders with proof images
        for ($i = 1; $i <= 3; $i++) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'service_id' => 2, // LesRide
                'status' => 'completed',
                'pickup_address' => "Test Pickup Address $i",
                'pickup_lat' => 14.5995 + ($i * 0.001),
                'pickup_lng' => 120.9842 + ($i * 0.001),
                'dropoff_address' => "Test Dropoff Address $i",
                'dropoff_lat' => 14.6091 + ($i * 0.001),
                'dropoff_lng' => 120.9928 + ($i * 0.001),
                'estimated_distance_m' => 1000 + ($i * 100),
                'estimated_fare' => 50 + ($i * 10),
                'actual_fare' => 50 + ($i * 10),
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'completed_at' => now()->subDays($i),
                'created_at' => now()->subDays($i + 1),
                'updated_at' => now()->subDays($i),
            ]);

            // Add proof images (using the same images from order 412 for testing)
            $proofImages = [
                'proof_images/412/VsFF5uzJcWPTeF4lrOLRlZxVASbmE9spiRosXUqF.jpg',
                'proof_images/412/6K2MSiEpmj4r82b4VotA560Ph2SRCeFGKfVhQGjc.jpg',
            ];

            // Add more images for variety
            if ($i == 2) {
                $proofImages[] = 'proof_images/412/t848ci0MQUWiI4N9luugLHi2MNCYHzsiv9uEI4e1.png';
            }
            if ($i == 3) {
                $proofImages = [
                    'proof_images/404/jUmiogk8cibq21PhKXQB92CpjBL8SN4GxCm5YwDc.jpg',
                    'proof_images/404/9cbdIIk1XDlL9fQ0eGknBiJymo2KanSyMOQKbKMw.jpg',
                    'proof_images/404/SP0MY35eCbA1nj3Vpj5DNn8ZRWxuR5oFWDEadVZZ.jpg',
                ];
            }

            $order->update([
                'proof_images' => json_encode($proofImages),
            ]);

            $this->command->info("✅ Created order #{$order->id} with " . count($proofImages) . " proof images");
        }

        // Create 2 completed orders WITHOUT proof images
        for ($i = 1; $i <= 2; $i++) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'service_id' => 2, // LesRide
                'status' => 'completed',
                'pickup_address' => "No Proof Pickup $i",
                'pickup_lat' => 14.5995 + ($i * 0.002),
                'pickup_lng' => 120.9842 + ($i * 0.002),
                'dropoff_address' => "No Proof Dropoff $i",
                'dropoff_lat' => 14.6091 + ($i * 0.002),
                'dropoff_lng' => 120.9928 + ($i * 0.002),
                'estimated_distance_m' => 800 + ($i * 50),
                'estimated_fare' => 40 + ($i * 5),
                'actual_fare' => 40 + ($i * 5),
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'completed_at' => now()->subDays($i + 3),
                'created_at' => now()->subDays($i + 4),
                'updated_at' => now()->subDays($i + 3),
            ]);

            $this->command->info("✅ Created order #{$order->id} WITHOUT proof images");
        }

        $this->command->info('');
        $this->command->info('🎉 Test data seeded successfully!');
        $this->command->info('   - 3 orders WITH proof images');
        $this->command->info('   - 2 orders WITHOUT proof images');
    }
}
