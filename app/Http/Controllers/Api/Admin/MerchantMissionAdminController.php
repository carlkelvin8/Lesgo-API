<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantMission;
use App\Models\MissionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantMissionAdminController extends Controller
{
    /**
     * Reset and reseed merchant mission templates (admin only, non-production).
     */
    public function reset(Request $request): JsonResponse
    {
        if (!app()->environment(['local', 'staging', 'testing'])) {
            return $this->error('This action is disabled in production.', 403);
        }

        MissionTemplate::where('target_audience', 'merchant')->delete();
        MerchantMission::truncate();

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
            MissionTemplate::create($template);
        }

        return $this->success([
            'templates_created' => count($templates),
        ], 'Merchant missions reset and seeded');
    }
}
