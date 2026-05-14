<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerMission;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerMissionController extends Controller
{
    /**
     * Get customer's missions for today
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $today = now()->toDateString();

            // Get or create missions for today
            $missions = $this->getOrCreateDailyMissions($user, $today);

            // Update progress for all missions
            foreach ($missions as $mission) {
                $this->updateMissionProgress($mission, $user, $today);
            }

            // Reload missions to get updated progress
            $missions = CustomerMission::where('user_id', $user->id)
                ->where('mission_date', $today)
                ->orderBy('id')
                ->get();

            return $this->success($missions, 'Missions retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve missions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Claim mission reward
     */
    public function claim(Request $request, CustomerMission $mission): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $mission->user_id !== $user->id) {
                return $this->error('Unauthorized', 401);
            }

            if (!$mission->is_completed) {
                return $this->error('Mission not completed yet', 400);
            }

            if ($mission->reward_claimed) {
                return $this->error('Reward already claimed', 400);
            }

            $mission->update([
                'reward_claimed' => true,
                'claimed_at' => now(),
            ]);

            // Here you could add logic to actually give the reward to the user
            // For example, add voucher to user's account, add points, etc.

            return $this->success($mission, 'Reward claimed successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to claim reward: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get or create daily missions for a user
     */
    private function getOrCreateDailyMissions(User $user, string $date): array
    {
        $missions = [];

        // Define daily mission templates
        $missionTemplates = [
            [
                'mission_type' => 'leseat_order',
                'title' => 'Book 1 LesEat today',
                'description' => 'Order food from any restaurant',
                'goal_target' => 1,
                'reward_type' => 'voucher',
                'reward_value' => 50.00,
            ],
            [
                'mission_type' => 'friend_referral',
                'title' => 'Refer 1 friend',
                'description' => 'Invite a friend to join LesGo',
                'goal_target' => 1,
                'reward_type' => 'voucher',
                'reward_value' => 100.00,
            ],
            [
                'mission_type' => 'app_review',
                'title' => 'Leave us a review',
                'description' => 'Rate our app on the store',
                'goal_target' => 1,
                'reward_type' => 'voucher',
                'reward_value' => 25.00,
            ],
            [
                'mission_type' => 'social_follow',
                'title' => 'Like our FB page',
                'description' => 'Follow us on social media',
                'goal_target' => 1,
                'reward_type' => 'voucher',
                'reward_value' => 25.00,
            ],
            [
                'mission_type' => 'lesride_orders',
                'title' => 'Book 5 LesRide',
                'description' => 'Complete 5 ride bookings',
                'goal_target' => 5,
                'reward_type' => 'voucher',
                'reward_value' => 200.00,
            ],
        ];

        foreach ($missionTemplates as $template) {
            $mission = CustomerMission::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'mission_type' => $template['mission_type'],
                    'mission_date' => $date,
                ],
                [
                    'title' => $template['title'],
                    'description' => $template['description'],
                    'current_progress' => 0,
                    'goal_target' => $template['goal_target'],
                    'is_completed' => false,
                    'reward_type' => $template['reward_type'],
                    'reward_value' => $template['reward_value'],
                    'reward_claimed' => false,
                ]
            );

            $missions[] = $mission;
        }

        return $missions;
    }

    /**
     * Update mission progress based on user activities
     */
    private function updateMissionProgress(CustomerMission $mission, User $user, string $date): void
    {
        $progress = 0;

        switch ($mission->mission_type) {
            case 'leseat_order':
                // Count completed LesEat orders today
                $progress = Order::where('customer_id', $user->id)
                    ->where('service_id', 3) // LesEat service ID
                    ->where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->count();
                break;

            case 'lesride_orders':
                // Count completed LesRide orders today
                $progress = Order::where('customer_id', $user->id)
                    ->where('service_id', 2) // LesRide service ID
                    ->where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->count();
                break;

            case 'friend_referral':
                // Count referrals made today (you'd need to implement referral tracking)
                // For now, keep it at 0 until referral system is implemented
                $progress = 0;
                break;

            case 'app_review':
                // This would need to be manually updated or tracked via external API
                // For now, keep it at 0 until review tracking is implemented
                $progress = 0;
                break;

            case 'social_follow':
                // This would need to be manually updated or tracked via social media APIs
                // For now, keep it at 0 until social media tracking is implemented
                $progress = 0;
                break;
        }

        // Update progress and completion status
        $isCompleted = $progress >= $mission->goal_target;
        
        $mission->update([
            'current_progress' => $progress,
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted && !$mission->completed_at ? now() : $mission->completed_at,
        ]);
    }
}