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
