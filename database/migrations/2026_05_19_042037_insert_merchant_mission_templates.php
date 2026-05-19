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
                'created_at' => now(),
                'updated_at' => now(),
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
                'created_at' => now(),
                'updated_at' => now(),
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
                'created_at' => now(),
                'updated_at' => now(),
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
                'created_at' => now(),
                'updated_at' => now(),
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
