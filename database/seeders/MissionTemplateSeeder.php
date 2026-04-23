<?php

namespace Database\Seeders;

use App\Models\MissionTemplate;
use Illuminate\Database\Seeder;

class MissionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            [
                'title' => 'Book 10 orders today',
                'description' => 'Complete 10 orders in a single day',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 10,
                'reward_amount' => 10.00,
                'reward_currency' => 'PHP',
                'service_code' => null,
                'is_active' => true,
            ],
            [
                'title' => 'Refer 1 friend today',
                'description' => 'Refer a new driver using your referral code',
                'type' => 'daily',
                'goal_type' => 'refer_friend',
                'goal_target' => 1,
                'reward_amount' => 10.00,
                'reward_currency' => 'PHP',
                'service_code' => null,
                'is_active' => true,
            ],
            [
                'title' => 'Achieve 25 bookings',
                'description' => 'Complete 25 total bookings',
                'type' => 'weekly',
                'goal_type' => 'complete_orders',
                'goal_target' => 25,
                'reward_amount' => 25.00,
                'reward_currency' => 'PHP',
                'service_code' => null,
                'is_active' => true,
            ],
            [
                'title' => 'Get 1 Lesride booking today',
                'description' => 'Complete 1 Lesride booking',
                'type' => 'daily',
                'goal_type' => 'specific_service',
                'goal_target' => 1,
                'reward_amount' => 5.00,
                'reward_currency' => 'PHP',
                'service_code' => 'lesride',
                'is_active' => true,
            ],
            [
                'title' => 'Get a 5 star rating today',
                'description' => 'Receive a 5-star rating from a customer',
                'type' => 'daily',
                'goal_type' => 'get_rating',
                'goal_target' => 5,
                'reward_amount' => 5.00,
                'reward_currency' => 'PHP',
                'service_code' => null,
                'is_active' => true,
            ],
        ];

        foreach ($missions as $mission) {
            MissionTemplate::updateOrCreate(
                ['title' => $mission['title']],
                $mission
            );
        }

        $this->command->info('Mission templates seeded successfully!');
    }
}
