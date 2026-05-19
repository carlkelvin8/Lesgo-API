<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = [
            [
                'title' => 'Complete 5 Orders Today',
                'description' => 'Complete 5 orders today to earn your reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 5,
                'reward_amount' => 50.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Achieve 10 Bookings',
                'description' => 'Complete 10 orders today for a bigger reward.',
                'type' => 'daily',
                'goal_type' => 'complete_orders',
                'goal_target' => 10,
                'reward_amount' => 100.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Get a 5-star Rating Today',
                'description' => 'Receive a 5-star rating from a customer today.',
                'type' => 'daily',
                'goal_type' => 'get_rating',
                'goal_target' => 1,
                'reward_amount' => 30.00,
                'reward_currency' => 'PHP',
                'is_active' => true,
                'service_code' => null,
                'target_audience' => 'merchant',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('mission_templates')->insert($templates);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mission_templates')->where('target_audience', 'merchant')->delete();
    }
};
