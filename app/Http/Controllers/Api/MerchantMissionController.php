<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MerchantMission;
use App\Models\MissionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MerchantMissionController extends Controller
{
    /**
     * Get merchant's missions for today
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Ensure user is a partner admin or has a partner relationship
        if (!$user->isPartnerAdmin() || !$user->partner) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a merchant',
            ], 403);
        }

        $partnerId = $user->partner->id;
        $today = Carbon::now()->toDateString();

        // Get all active mission templates for merchants
        $templates = MissionTemplate::where('is_active', true)
            ->where('target_audience', 'merchant')
            ->where('type', 'daily')
            ->get();

        // Get or create merchant missions for today
        $missions = [];
        foreach ($templates as $template) {
            $merchantMission = MerchantMission::firstOrCreate(
                [
                    'partner_id' => $partnerId,
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
            $this->updateMissionProgress($merchantMission, $template, $partnerId, $today);

            $missions[] = [
                'id' => $merchantMission->id,
                'title' => $template->title,
                'description' => $template->description,
                'reward' => $template->reward_currency . ' ' . number_format($template->reward_amount, 0),
                'reward_amount' => (float) $template->reward_amount,
                'current_progress' => $merchantMission->current_progress,
                'goal_target' => $merchantMission->goal_target,
                'progress' => $merchantMission->progress_percentage,
                'is_completed' => $merchantMission->is_completed,
                'reward_claimed' => $merchantMission->reward_claimed,
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
        
        if (!$user->isPartnerAdmin() || !$user->partner) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a merchant',
            ], 403);
        }

        $merchantMission = MerchantMission::where('id', $missionId)
            ->where('partner_id', $user->partner->id)
            ->first();

        if (!$merchantMission) {
            return response()->json([
                'success' => false,
                'message' => 'Mission not found',
            ], 404);
        }

        if (!$merchantMission->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Mission not completed yet',
            ], 400);
        }

        if ($merchantMission->reward_claimed) {
            return response()->json([
                'success' => false,
                'message' => 'Reward already claimed',
            ], 400);
        }

        // Mark as claimed
        $merchantMission->update([
            'reward_claimed' => true,
            'claimed_at' => now(),
        ]);

        // Add reward to merchant's wallet (using user's wallet)
        $template = $merchantMission->missionTemplate;
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
    private function updateMissionProgress(MerchantMission $mission, MissionTemplate $template, int $partnerId, string $date): void
    {
        $progress = 0;

        switch ($template->goal_type) {
            case 'complete_orders':
                // Count completed orders for today
                $progress = \App\Models\Order::whereHas('partner', function ($q) use ($partnerId) {
                    $q->where('id', $partnerId);
                })
                ->where('status', 'completed')
                ->whereDate('updated_at', $date)
                ->count();
                break;

            case 'specific_service':
                // Count completed orders for specific service
                $progress = \App\Models\Order::whereHas('partner', function ($q) use ($partnerId) {
                    $q->where('id', $partnerId);
                })
                ->whereHas('service', function ($q) use ($template) {
                    $q->where('code', $template->service_code);
                })
                ->where('status', 'completed')
                ->whereDate('updated_at', $date)
                ->count();
                break;

            case 'get_rating':
                // Check if merchant got a 5-star rating today
                // Assuming RatingReview has a partner_id or we can check via orders
                // Actually, RatingReview might just have order_id and user_id. Let's check via orders.
                $hasRating = \App\Models\RatingReview::whereHas('order', function ($q) use ($partnerId) {
                        $q->where('partner_id', $partnerId);
                    })
                    ->where('overall_rating', '>=', $template->goal_target)
                    ->whereDate('created_at', $date)
                    ->exists();
                $progress = $hasRating ? 1 : 0;
                break;
                
            case 'refer_friend':
                // For merchants, referral logic if any
                // Assuming standard referral logic using the user's code
                $partner = \App\Models\Partner::find($partnerId);
                if ($partner && $partner->user) {
                    $progress = \App\Models\User::where('referred_by', $partner->user->referral_code)
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
