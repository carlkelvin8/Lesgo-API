<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MerchantMissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'title' => 'Book 10 orders today',
                'description' => 'Complete 10 orders as a merchant today to earn this reward.',
                'type' => 'daily',
                'target_audience' => 'merchant',
                'goal_type' => 'complete_orders',
                'goal_target' => 10,
                'reward_amount' => 10.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
            ],
            [
                'title' => 'Achieve 25 bookings',
                'description' => 'Complete 25 orders as a merchant today to earn this reward.',
                'type' => 'daily',
                'target_audience' => 'merchant',
                'goal_type' => 'complete_orders',
                'goal_target' => 25,
                'reward_amount' => 25.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
            ],
            [
                'title' => 'Get a 5 star rating today',
                'description' => 'Receive at least one 5-star rating on an order today.',
                'type' => 'daily',
                'target_audience' => 'merchant',
                'goal_type' => 'get_rating',
                'goal_target' => 5,
                'reward_amount' => 5.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            \App\Models\MissionTemplate::updateOrCreate(
                ['title' => $template['title'], 'target_audience' => $template['target_audience']],
                $template
            );
        }
    }
}
