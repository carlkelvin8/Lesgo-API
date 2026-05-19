<?php

namespace Database\Seeders;

use App\Models\MissionTemplate;
use Illuminate\Database\Seeder;

class MissionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'title' => 'Complete 3 Deliveries',
                'description' => 'Complete 3 orders today to earn your reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 3,
                'reward_amount' => 50.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'driver',
            ],
            [
                'title' => 'Complete 5 Deliveries',
                'description' => 'Complete 5 orders today for a bigger reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 5,
                'reward_amount' => 100.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'driver',
            ],
            [
                'title' => 'LesEat Specialist',
                'description' => 'Complete 2 LesEat food delivery orders today.',
                'type' => 'daily',
                'goal_type' => 'specific_service',
                'goal_target' => 2,
                'reward_amount' => 40.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => 'LESEAT',
                'target_audience' => 'driver',
            ],
            [
                'title' => 'LesBuy Champion',
                'description' => 'Complete 2 LesBuy shopping orders today.',
                'type' => 'daily',
                'goal_type' => 'specific_service',
                'goal_target' => 2,
                'reward_amount' => 40.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => 'LESBUY',
                'target_audience' => 'driver',
            ],
            [
                'title' => 'Top Rated Driver',
                'description' => 'Receive a 5-star rating from a customer today.',
                'type' => 'daily',
                'goal_type' => 'get_rating',
                'goal_target' => 1,
                'reward_amount' => 30.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'driver',
            ],
            // Merchant Missions
            [
                'title' => 'Book 10 orders today',
                'description' => 'Book 10 orders today to earn your reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 10,
                'reward_amount' => 10.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
            ],
            [
                'title' => 'Refer 1 friend today',
                'description' => 'Refer 1 friend today to earn your reward.',
                'type' => 'daily',
                'goal_type' => 'referral',
                'goal_target' => 1,
                'reward_amount' => 10.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
            ],
            [
                'title' => 'Achieve 25 bookings',
                'description' => 'Achieve 25 bookings to earn your reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 25,
                'reward_amount' => 25.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
            ],
            [
                'title' => 'Achieve 50 bookings',
                'description' => 'Achieve 50 bookings to earn your reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 50,
                'reward_amount' => 5.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
            ],
            [
                'title' => 'Achieve 100 bookings',
                'description' => 'Achieve 100 bookings to earn your reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 100,
                'reward_amount' => 5.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
            ],
        ];

        foreach ($templates as $template) {
            MissionTemplate::firstOrCreate(
                ['title' => $template['title']],
                $template
            );
        }

        $this->command->info('✅ Mission templates seeded successfully!');
    }
}
