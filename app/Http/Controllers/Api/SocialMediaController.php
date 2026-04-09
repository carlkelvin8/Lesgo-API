<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialShare;
use App\Models\Order;
use App\Services\SocialMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SocialMediaController extends Controller
{
    protected SocialMediaService $socialMediaService;

    public function __construct(SocialMediaService $socialMediaService)
    {
        $this->socialMediaService = $socialMediaService;
    }

    /**
     * Get supported social media platforms.
     */
    public function platforms(): JsonResponse
    {
        $platforms = SocialShare::getSupportedPlatforms();
        
        return response()->json([
            'success' => true,
            'message' => 'Supported platforms retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $platforms,
        ]);
    }

    /**
     * Generate share content for an order.
     */
    public function generateOrderShare(Request $request, Order $order): JsonResponse
    {
        // Check if user owns the order
        if ($order->customer_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only share your own orders',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $request->validate([
            'platform' => [
                'required',
                'string',
                Rule::in(array_keys(SocialShare::getSupportedPlatforms()))
            ],
            'share_type' => [
                'required',
                'string',
                Rule::in(['order_completed', 'service_review'])
            ],
            'rating' => 'nullable|integer|min:1|max:5', // Required for service_review
        ]);

        if ($request->share_type === 'service_review' && !$request->rating) {
            return response()->json([
                'success' => false,
                'message' => 'Rating is required for service review shares',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        // Generate share content based on type
        if ($request->share_type === 'order_completed') {
            $content = $this->socialMediaService->generateOrderCompletionShare($order, $request->platform);
        } else {
            $content = $this->socialMediaService->generateServiceReviewShare($order, $request->rating, $request->platform);
        }

        // Create share record
        $share = $this->socialMediaService->createShare(
            auth()->user(),
            $request->platform,
            $request->share_type,
            $content,
            $order
        );

        return response()->json([
            'success' => true,
            'message' => 'Share content generated successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'share' => $share,
                'share_url' => $share->generateShareUrl(),
                'platform_config' => $share->getPlatformConfig(),
                'open_graph' => $share->getOpenGraphData(),
            ],
        ], 201);
    }

    /**
     * Generate referral share content.
     */
    public function generateReferralShare(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => [
                'required',
                'string',
                Rule::in(array_keys(SocialShare::getSupportedPlatforms()))
            ],
        ]);

        $content = $this->socialMediaService->generateReferralShare(auth()->user(), $request->platform);
        
        $share = $this->socialMediaService->createShare(
            auth()->user(),
            $request->platform,
            'referral',
            $content
        );

        return response()->json([
            'success' => true,
            'message' => 'Referral share content generated successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'share' => $share,
                'share_url' => $share->generateShareUrl(),
                'platform_config' => $share->getPlatformConfig(),
                'open_graph' => $share->getOpenGraphData(),
            ],
        ], 201);
    }

    /**
     * Generate milestone share content.
     */
    public function generateMilestoneShare(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => [
                'required',
                'string',
                Rule::in(array_keys(SocialShare::getSupportedPlatforms()))
            ],
            'milestone' => [
                'required',
                'string',
                Rule::in(['first_order', '10_orders', '50_orders', '100_orders', 'loyal_customer', 'top_reviewer'])
            ],
        ]);

        $content = $this->socialMediaService->generateMilestoneShare(
            auth()->user(),
            $request->milestone,
            $request->platform
        );
        
        $share = $this->socialMediaService->createShare(
            auth()->user(),
            $request->platform,
            'milestone',
            $content
        );

        return response()->json([
            'success' => true,
            'message' => 'Milestone share content generated successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'share' => $share,
                'share_url' => $share->generateShareUrl(),
                'platform_config' => $share->getPlatformConfig(),
                'open_graph' => $share->getOpenGraphData(),
            ],
        ], 201);
    }

    /**
     * Get user's social media shares.
     */
    public function myShares(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|string',
            'share_type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = SocialShare::where('user_id', auth()->id())
            ->with(['order'])
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->when($request->share_type, fn($q) => $q->where('share_type', $request->share_type))
            ->orderBy('shared_at', 'desc');

        $shares = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Your shares retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $shares->items(),
            'meta' => [
                'total' => $shares->total(),
                'per_page' => $shares->perPage(),
                'current_page' => $shares->currentPage(),
                'last_page' => $shares->lastPage(),
                'from' => $shares->firstItem(),
                'to' => $shares->lastItem(),
                'has_more' => $shares->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get sharing analytics for the user.
     */
    public function analytics(): JsonResponse
    {
        $analytics = $this->socialMediaService->getUserSharingAnalytics(auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Sharing analytics retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $analytics,
        ]);
    }

    /**
     * Track share engagement (clicks, views, etc.).
     */
    public function trackEngagement(Request $request, SocialShare $share): JsonResponse
    {
        // Verify user owns the share or allow public tracking for views/clicks
        if ($share->user_id !== auth()->id() && !in_array($request->action, ['view', 'click'])) {
            return response()->json([
                'success' => false,
                'message' => 'You can only track engagement for your own shares',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        $request->validate([
            'action' => 'required|in:click,view,update_engagement',
            'data' => 'nullable|array', // For update_engagement action
        ]);

        $this->socialMediaService->trackEngagement(
            $share,
            $request->action,
            $request->data ?? []
        );

        return response()->json([
            'success' => true,
            'message' => 'Engagement tracked successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'total_engagement' => $share->fresh()->getTotalEngagement(),
                'engagement_rate' => $share->fresh()->getEngagementRate(),
            ],
        ]);
    }

    /**
     * Get platform-specific sharing guidelines.
     */
    public function platformGuidelines(string $platform): JsonResponse
    {
        if (!array_key_exists($platform, SocialShare::getSupportedPlatforms())) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported platform',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $guidelines = $this->socialMediaService->getPlatformGuidelines($platform);

        return response()->json([
            'success' => true,
            'message' => 'Platform guidelines retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $guidelines,
        ]);
    }

    /**
     * Get public share content (for share URLs).
     */
    public function publicShare(SocialShare $share): JsonResponse
    {
        if (!$share->is_public || !$share->is_active || $share->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Share not available',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 404);
        }

        // Track view
        $share->incrementViews();

        return response()->json([
            'success' => true,
            'message' => 'Share content retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => [
                'title' => $share->share_title,
                'description' => $share->share_description,
                'image_url' => $share->share_image_url,
                'platform' => $share->platform,
                'share_type' => $share->share_type,
                'shared_at' => $share->shared_at,
                'open_graph' => $share->getOpenGraphData(),
                'user' => [
                    'name' => $share->user->name,
                    'avatar' => $share->user->avatar ?? null,
                ],
                'order' => $share->order ? [
                    'id' => $share->order->id,
                    'service_name' => $share->order->service->name ?? null,
                    'completed_at' => $share->order->completed_at,
                ] : null,
            ],
        ]);
    }

    /**
     * Get trending shares (public).
     */
    public function trending(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'nullable|string',
            'share_type' => 'nullable|string',
            'days' => 'nullable|integer|min:1|max:90',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $days = $request->days ?? 7;
        $limit = $request->limit ?? 20;

        $query = SocialShare::public()
            ->active()
            ->notExpired()
            ->with(['user', 'order'])
            ->where('shared_at', '>=', now()->subDays($days))
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->when($request->share_type, fn($q) => $q->where('share_type', $request->share_type))
            ->popular()
            ->orderByRaw('(clicks + views + likes + shares + comments) DESC')
            ->limit($limit);

        $shares = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Trending shares retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $shares,
            'meta' => [
                'period_days' => $days,
                'total_results' => $shares->count(),
            ],
        ]);
    }

    /**
     * Get sharing statistics (public).
     */
    public function statistics(): JsonResponse
    {
        $platformStats = SocialShare::getPlatformStats();
        $shareTypeStats = SocialShare::getShareTypeStats();
        $topShares = SocialShare::getTopPerformingShares(10);

        return response()->json([
            'success' => true,
            'message' => 'Sharing statistics retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => [
                'platform_stats' => $platformStats,
                'share_type_stats' => $shareTypeStats,
                'top_performing_shares' => $topShares,
                'total_shares' => SocialShare::count(),
                'total_engagement' => SocialShare::sum(\DB::raw('clicks + views + likes + shares + comments')),
                'active_sharers' => SocialShare::distinct('user_id')->count(),
            ],
        ]);
    }
}