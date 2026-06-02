<?php

namespace App\Http\Controllers\Api;

use App\Models\SocialShare;
use App\Models\Order;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SocialMediaController extends Controller
{
    // Public endpoints

    /**
     * Get public share details.
     */
    public function publicShare($shareId): JsonResponse
    {
        $share = SocialShare::with('user:id,name,profile_photo_url')
            ->where('id', $shareId)
            ->where('is_public', true)
            ->first();

        if (!$share) {
            return $this->error('Share not found.', 404);
        }

        // Increment views
        $share->increment('views');

        return $this->success($share, 'Public share retrieved successfully');
    }

    /**
     * Get trending content.
     */
    public function trending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'period' => 'nullable|in:day,week,month',
        ]);

        $limit = (int) ($validated['limit'] ?? 10);
        $days = $validated['period'] === 'day' ? 1 : ($validated['period'] === 'month' ? 30 : 7);

        try {
            $trending = \App\Models\SocialShare::where('is_public', true)
                ->where('created_at', '>=', now()->subDays($days))
                ->orderByDesc('views')
                ->limit($limit)
                ->get();

            return $this->success($trending, 'Trending content retrieved successfully');
        } catch (\Exception $e) {
            // Return empty if SocialShare table doesn't exist
            return $this->success([], 'Trending content retrieved successfully');
        }
    }

    /**
     * Get social media statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalShares = \App\Models\SocialShare::where('is_public', true)->count();
            $totalViews = \App\Models\SocialShare::where('is_public', true)->sum('views');
            $totalClicks = \App\Models\SocialShare::where('is_public', true)->sum('clicks');
            $engagementRate = $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0;

            return $this->success([
                'total_shares' => $totalShares,
                'total_views' => $totalViews,
                'total_clicks' => $totalClicks,
                'engagement_rate' => $engagementRate,
            ], 'Social media statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->success([
                'total_shares' => 0,
                'total_views' => 0,
                'total_clicks' => 0,
                'engagement_rate' => 0,
            ], 'Social media statistics retrieved successfully');
        }
    }

    // Protected endpoints

    /**
     * Get supported social platforms.
     */
    public function platforms(): JsonResponse
    {
        return $this->success([
            [
                'id' => 'facebook',
                'name' => 'Facebook',
                'icon_url' => 'https://example.com/icons/facebook.png',
                'enabled' => true,
                'max_characters' => 63206,
                'supported_features' => ['text', 'image', 'link'],
            ],
            [
                'id' => 'twitter',
                'name' => 'Twitter/X',
                'icon_url' => 'https://example.com/icons/twitter.png',
                'enabled' => true,
                'max_characters' => 280,
                'supported_features' => ['text', 'image', 'link'],
            ],
            [
                'id' => 'instagram',
                'name' => 'Instagram',
                'icon_url' => 'https://example.com/icons/instagram.png',
                'enabled' => true,
                'max_characters' => 2200,
                'supported_features' => ['text', 'image'],
            ],
            [
                'id' => 'tiktok',
                'name' => 'TikTok',
                'icon_url' => 'https://example.com/icons/tiktok.png',
                'enabled' => false,
                'max_characters' => 2200,
                'supported_features' => ['video'],
            ],
        ], 'Social platforms retrieved successfully');
    }

    /**
     * Get platform-specific sharing guidelines.
     */
    public function platformGuidelines($platform): JsonResponse
    {
        $guidelines = [
            'facebook' => [
                'name' => 'Facebook',
                'best_practices' => [
                    'Use high-quality images',
                    'Keep text engaging and concise',
                    'Include a clear call-to-action',
                ],
                'image_size' => '1200x630 pixels recommended',
                'max_text_length' => 63206,
            ],
            'twitter' => [
                'name' => 'Twitter/X',
                'best_practices' => [
                    'Keep messages under 280 characters',
                    'Use relevant hashtags',
                    'Include eye-catching images',
                ],
                'image_size' => '1200x675 pixels recommended',
                'max_text_length' => 280,
            ],
            'instagram' => [
                'name' => 'Instagram',
                'best_practices' => [
                    'Use square or portrait images',
                    'Write compelling captions',
                    'Use relevant hashtags (up to 30)',
                ],
                'image_size' => '1080x1080 pixels recommended',
                'max_text_length' => 2200,
            ],
        ];

        if (!isset($guidelines[$platform])) {
            return $this->error('Platform guidelines not found.', 404);
        }

        return $this->success($guidelines[$platform], 'Platform guidelines retrieved successfully');
    }

    /**
     * Generate a share for an order completion.
     */
    public function generateOrderShare(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();

        $order = Order::find($orderId);

        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        if ((int) $order->customer_id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        if ($order->status !== 'completed') {
            return $this->error('Can only share completed orders.', 400);
        }

        $share = SocialShare::create([
            'user_id' => $user->id,
            'share_type' => 'order_completed',
            'order_id' => $order->id,
            'platform' => 'other',
            'share_title' => 'My LeSGo Order Completed!',
            'share_description' => "Just completed an order on LeSGo! 🎉 Order #{$order->id}",
            'share_url' => url("/share/order/{$order->id}/" . Str::random(8)),
            'share_image_url' => null,
            'is_public' => true,
            'share_metadata' => [
                'order_id' => $order->id,
                'service' => $order->service->name ?? 'LeSGo',
            ],
        ]);

        return $this->created($share, 'Order share generated successfully');
    }

    /**
     * Generate a referral share.
     */
    public function generateReferralShare(Request $request): JsonResponse
    {
        $user = $request->user();
        $platform = $request->input('platform', 'other');

        // Ensure user has a referral code
        if (!$user->referral_code) {
            $user->update([
                'referral_code' => strtoupper(Str::random(8)),
            ]);
            $user->refresh();
        }

        $share = SocialShare::create([
            'user_id' => $user->id,
            'share_type' => 'referral',
            'platform' => $platform,
            'share_title' => 'Join LeSGo using my referral code!',
            'share_description' => "Sign up on LeSGo and use my referral code: {$user->referral_code} to get bonuses! 🎁",
            'share_url' => url("/register?ref={$user->referral_code}"),
            'share_image_url' => null,
            'is_public' => true,
            'share_metadata' => [
                'referral_code' => $user->referral_code,
            ],
        ]);

        return $this->created($share, 'Referral share generated successfully');
    }

    /**
     * Generate a milestone share (e.g., 100 orders).
     */
    public function generateMilestoneShare(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalOrders = Order::where('customer_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $milestones = [10, 50, 100, 250, 500, 1000];
        $nextMilestone = collect($milestones)->first(fn ($m) => $m > $totalOrders);

        if (!$nextMilestone) {
            return $this->error('No more milestones available.', 400);
        }

        $share = SocialShare::create([
            'user_id' => $user->id,
            'share_type' => 'milestone',
            'platform' => 'other',
            'share_title' => "LeSGo Milestone: {$totalOrders} Orders!",
            'share_description' => "I've completed {$totalOrders} orders on LeSGo! 🚀 Next milestone: {$nextMilestone} orders!",
            'share_url' => url("/share/milestone/{$user->id}/" . Str::random(8)),
            'share_image_url' => null,
            'is_public' => true,
            'share_metadata' => [
                'total_orders' => $totalOrders,
                'next_milestone' => $nextMilestone,
            ],
        ]);

        return $this->created($share, 'Milestone share generated successfully');
    }

    /**
     * Confirm social follow (e.g. Facebook page) for mission progress.
     */
    public function confirmSocialFollow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|in:facebook,instagram,twitter',
        ]);

        $user = $request->user();

        $share = SocialShare::firstOrCreate(
            [
                'user_id'    => $user->id,
                'share_type' => 'social_follow',
                'platform'   => $validated['platform'],
            ],
            [
                'share_title'       => 'Followed LeSGo on ' . ucfirst($validated['platform']),
                'share_description' => 'Confirmed social follow for mission progress.',
                'share_url'         => url('/social/follow/' . $validated['platform']),
                'is_public'         => false,
            ]
        );

        return $this->success($share, 'Social follow recorded');
    }

    /**
     * Get current user's shares.
     */
    public function myShares(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'share_type' => 'nullable|in:order_completion,referral,milestone',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SocialShare::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if (!empty($validated['share_type'])) {
            $query->where('share_type', $validated['share_type']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $shares = $query->paginate($perPage);

        return $this->success($shares, 'My shares retrieved successfully');
    }

    /**
     * Get social media analytics for current user.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();

        $myShares = SocialShare::where('user_id', $user->id)->get();
        $totalViews = $myShares->sum('views');
        $totalClicks = $myShares->sum('clicks');
        $engagementRate = $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0;

        $topShare = $myShares->sortByDesc('views')->first();

        return $this->success([
            'my_shares' => $myShares->count(),
            'total_views' => $totalViews,
            'total_clicks' => $totalClicks,
            'engagement_rate' => $engagementRate,
            'top_performing_share' => $topShare ? [
                'id' => $topShare->id,
                'title' => $topShare->title,
                'views' => $topShare->views,
                'clicks' => $topShare->clicks,
            ] : null,
        ], 'Social media analytics retrieved successfully');
    }

    /**
     * Track engagement on a share (view, click).
     */
    public function trackEngagement(Request $request, $shareId): JsonResponse
    {
        $validated = $request->validate([
            'event' => 'required|in:view,click,like,share',
        ]);

        $share = SocialShare::find($shareId);

        if (!$share) {
            return $this->error('Share not found.', 404);
        }

        match ($validated['event']) {
            'view' => $share->increment('views'),
            'click' => $share->increment('clicks'),
            'like' => $share->increment('likes'),
            'share' => $share->increment('shares'),
        };

        return $this->success([
            'share_id' => $shareId,
            'event' => $validated['event'],
            'tracked_at' => now()->toISOString(),
        ], 'Engagement tracked successfully');
    }
}
