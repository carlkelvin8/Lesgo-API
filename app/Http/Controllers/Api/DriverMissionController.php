<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverMission;
use App\Models\MissionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverMissionController extends Controller
{
    /**
     * Get driver's missions for today
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isDriver() || !$user->driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a driver',
            ], 403);
        }

        $driverProfileId = $user->driverProfile->id;
        $today = now()->toDateString();

        // Get all active mission templates
        $templates = MissionTemplate::where('is_active', true)
            ->where('type', 'daily') // For now, only daily missions
            ->get();

        // Get or create driver missions for today
        $missions = [];
        foreach ($templates as $template) {
            $driverMission = DriverMission::firstOrCreate(
                [
                    'driver_profile_id' => $driverProfileId,
                    'mission_template_id' => $template->id,
                    'mission_date' => $today,
                ],
                [
                    'current_progress' => 0,
                    'goal_target' => $template->goal_target,
                    'is_completed' => false,
                ]
            );

            // Update progress based on actual data
            $this->updateMissionProgress($driverMission, $template, $driverProfileId, $today);

            $missions[] = [
                'id' => $driverMission->id,
                'title' => $template->title,
                'description' => $template->description,
                'reward' => $template->reward_currency . ' ' . number_format($template->reward_amount, 0),
                'reward_amount' => (float) $template->reward_amount,
                'current_progress' => $driverMission->current_progress,
                'goal_target' => $driverMission->goal_target,
                'progress' => $driverMission->progress_percentage,
                'is_completed' => $driverMission->is_completed,
                'reward_claimed' => $driverMission->reward_claimed,
            ];
        }

        return response()->json([
            'success' => true,
            'missions' => $missions,
        ]);
    }

    /**
     * Claim mission reward
     */
    public function claim(Request $request, int $missionId): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isDriver() || !$user->driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a driver',
            ], 403);
        }

        $driverMission = DriverMission::where('id', $missionId)
            ->where('driver_profile_id', $user->driverProfile->id)
            ->first();

        if (!$driverMission) {
            return response()->json([
                'success' => false,
                'message' => 'Mission not found',
            ], 404);
        }

        if (!$driverMission->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Mission not completed yet',
            ], 400);
        }

        if ($driverMission->reward_claimed) {
            return response()->json([
                'success' => false,
                'message' => 'Reward already claimed',
            ], 400);
        }

        // Mark as claimed
        $driverMission->update([
            'reward_claimed' => true,
            'claimed_at' => now(),
        ]);

        // Add reward to driver's wallet
        $template = $driverMission->missionTemplate;
        $wallet = $user->wallet;
        if ($wallet) {
            $wallet->increment('balance', $template->reward_amount);
            
            // Create wallet transaction
            \App\Models\WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $template->reward_amount,
                'description' => 'Mission reward: ' . $template->title,
                'created_by' => $user->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reward claimed successfully',
            'reward_amount' => $template->reward_amount,
        ]);
    }

    /**
     * Update mission progress based on actual data
     */
    private function updateMissionProgress(DriverMission $mission, MissionTemplate $template, int $driverProfileId, string $date): void
    {
        $progress = 0;

        switch ($template->goal_type) {
            case 'complete_orders':
                // Count completed orders for today
                $progress = \App\Models\Order::whereHas('driverProfile', function ($q) use ($driverProfileId) {
                    $q->where('id', $driverProfileId);
                })
                ->where('status', 'completed')
                ->whereDate('updated_at', $date)
                ->count();
                break;

            case 'specific_service':
                // Count completed orders for specific service
                $progress = \App\Models\Order::whereHas('driverProfile', function ($q) use ($driverProfileId) {
                    $q->where('id', $driverProfileId);
                })
                ->whereHas('service', function ($q) use ($template) {
                    $q->where('code', $template->service_code);
                })
                ->where('status', 'completed')
                ->whereDate('updated_at', $date)
                ->count();
                break;

            case 'get_rating':
                // Check if driver got a 5-star rating today
                $hasRating = \App\Models\RatingReview::where('driver_id', $driverProfileId)
                    ->where('overall_rating', '>=', $template->goal_target)
                    ->whereDate('created_at', $date)
                    ->exists();
                $progress = $hasRating ? 1 : 0;
                break;

            case 'refer_friend':
                // Count referrals today (users who used driver's referral code)
                $user = \App\Models\User::whereHas('driverProfile', function ($q) use ($driverProfileId) {
                    $q->where('id', $driverProfileId);
                })->first();
                
                if ($user) {
                    $progress = \App\Models\User::where('referred_by', $user->referral_code)
                        ->whereDate('created_at', $date)
                        ->count();
                }
                break;
        }

        // Update progress
        $isCompleted = $progress >= $template->goal_target;
        $mission->update([
            'current_progress' => $progress,
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted && !$mission->is_completed ? now() : $mission->completed_at,
        ]);
    }
}
