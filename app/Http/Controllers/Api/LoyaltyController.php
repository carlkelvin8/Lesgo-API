<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    /**
     * List redeemable loyalty rewards for the authenticated user.
     */
    public function rewards(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $catalog = collect(config('loyalty.rewards', []))->map(function (array $reward) use ($user) {
            return [
                'id' => $reward['id'],
                'title' => $reward['title'],
                'description' => $reward['description'],
                'points_cost' => $reward['points_cost'],
                'reward_type' => $reward['reward_type'] ?? 'voucher',
                'available' => ($user->points ?? 0) >= ($reward['points_cost'] ?? 0),
            ];
        })->values();

        return $this->success($catalog, 'Loyalty rewards retrieved successfully');
    }

    /**
     * Redeem a loyalty reward and deduct points.
     */
    public function redeem(Request $request, int $rewardId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $reward = collect(config('loyalty.rewards', []))->firstWhere('id', $rewardId);
        if (!$reward) {
            return $this->error('Reward not found', 404);
        }

        $pointsCost = (int) ($reward['points_cost'] ?? 0);
        if ($pointsCost <= 0) {
            return $this->error('Invalid reward configuration', 422);
        }

        return DB::transaction(function () use ($user, $reward, $pointsCost, $rewardId) {
            $lockedUser = $user->newQuery()->lockForUpdate()->find($user->id);
            $currentPoints = (int) ($lockedUser->points ?? 0);

            if ($currentPoints < $pointsCost) {
                return $this->error(
                    "Insufficient points. You need {$pointsCost} points but have {$currentPoints}.",
                    422
                );
            }

            $remaining = $currentPoints - $pointsCost;
            $lockedUser->update(['points' => $remaining]);

            return $this->success([
                'reward_id' => $rewardId,
                'reward_title' => $reward['title'],
                'voucher_code' => $reward['voucher_code'] ?? null,
                'points_spent' => $pointsCost,
                'remaining_points' => $remaining,
            ], 'Reward redeemed successfully');
        });
    }
}
