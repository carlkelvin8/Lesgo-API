<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    /**
     * Referral dashboard stats for the authenticated user.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $referralCode = $user->referral_code ?? strtoupper(substr(md5((string) $user->id), 0, 8));

        if (empty($user->referral_code)) {
            $user->update(['referral_code' => $referralCode]);
        }

        $referredQuery = User::query()->where(function ($q) use ($user, $referralCode) {
            $q->where('referred_by', $referralCode)
              ->orWhere('referred_by', (string) $user->id);
        });

        $invitesSent = (clone $referredQuery)->count();
        $friendsJoined = (clone $referredQuery)
            ->whereNotNull('phone_verified_at')
            ->count();

        $pointsPerJoin = (int) config('loyalty.referral_points_per_join', 100);
        $pointsEarned = $friendsJoined * $pointsPerJoin;

        $pendingRewards = (clone $referredQuery)
            ->whereNull('phone_verified_at')
            ->count();

        $recentRewards = (clone $referredQuery)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (User $referred) use ($pointsPerJoin) {
                $joined = $referred->phone_verified_at !== null;

                return [
                    'title' => $referred->name ?: 'Friend',
                    'friend_name' => $referred->name,
                    'status' => $joined ? 'completed' : 'pending',
                    'points' => $joined ? $pointsPerJoin : 0,
                    'date' => optional($referred->created_at)->toISOString(),
                    'created_at' => optional($referred->created_at)->toISOString(),
                ];
            })
            ->values();

        return $this->success([
            'referral_code' => $referralCode,
            'invites_sent' => $invitesSent,
            'friends_joined' => $friendsJoined,
            'pending_rewards' => $pendingRewards,
            'points_earned' => $pointsEarned > 0 ? $pointsEarned : (int) ($user->points ?? 0),
            'recent_rewards' => $recentRewards,
        ], 'Referral dashboard retrieved successfully');
    }
}
