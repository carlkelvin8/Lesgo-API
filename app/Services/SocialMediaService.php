<?php

namespace App\Services;

use App\Models\SocialShare;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SocialMediaService
{
    /**
     * Generate share content for an order completion.
     */
    public function generateOrderCompletionShare(Order $order, string $platform): array
    {
        $user = $order->customer;
        $service = $order->service;
        
        $templates = [
            'facebook' => [
                'title' => "Just completed my {$service->name} order with LeSGo! 🚚✨",
                'description' => "Fast, reliable, and professional service. Highly recommend LeSGo for all your delivery needs! #LeSGo #Delivery #Philippines",
            ],
            'twitter' => [
                'title' => "Just completed my {$service->name} order with @LeSGoPH! 🚚✨ Fast, reliable, and professional service. #LeSGo #Delivery",
                'description' => "",
            ],
            'instagram' => [
                'title' => "LeSGo Delivery Success! 📦✨",
                'description' => "Just completed my {$service->name} order with LeSGo! The service was fast, reliable, and professional. Highly recommend for all your delivery needs! 🚚\n\n#LeSGo #Delivery #Philippines #OrderCompleted #FastDelivery #ReliableService",
            ],
            'linkedin' => [
                'title' => "Excellent Service Experience with LeSGo",
                'description' => "I recently used LeSGo for {$service->name} and was impressed by their professional service, timely delivery, and user-friendly platform. Great example of how technology can improve logistics in the Philippines.",
            ],
            'whatsapp' => [
                'title' => "Just completed my {$service->name} order with LeSGo! Fast and reliable service. Check them out: ",
                'description' => "",
            ],
            'telegram' => [
                'title' => "Just completed my {$service->name} order with LeSGo! 🚚 Fast and reliable delivery service.",
                'description' => "",
            ],
        ];

        $template = $templates[$platform] ?? $templates['facebook'];
        
        return [
            'title' => $template['title'],
            'description' => $template['description'],
            'image_url' => $this->generateOrderCompletionImage($order),
            'share_url' => $this->generateShareUrl('order', $order->id),
            'metadata' => [
                'order_id' => $order->id,
                'service_name' => $service->name,
                'completion_date' => $order->completed_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Generate share content for a service review.
     */
    public function generateServiceReviewShare(Order $order, int $rating, string $platform): array
    {
        $service = $order->service;
        $stars = str_repeat('⭐', $rating);
        
        $templates = [
            'facebook' => [
                'title' => "Rated my LeSGo {$service->name} experience {$stars}!",
                'description' => "Great service and professional drivers. LeSGo makes delivery easy and reliable! #LeSGo #ServiceReview #Delivery",
            ],
            'twitter' => [
                'title' => "Rated my @LeSGoPH {$service->name} experience {$stars}! Great service and professional drivers. #LeSGo #ServiceReview",
                'description' => "",
            ],
            'instagram' => [
                'title' => "Service Review: {$stars}",
                'description' => "Just rated my LeSGo {$service->name} experience {$stars}! Great service, professional drivers, and reliable delivery. LeSGo makes logistics easy! 🚚📦\n\n#LeSGo #ServiceReview #Delivery #Philippines #CustomerExperience",
            ],
            'linkedin' => [
                'title' => "Service Review: LeSGo Delivery Platform",
                'description' => "Rated my recent {$service->name} experience with LeSGo {$stars}. Impressed by their professional approach, reliable service, and user-friendly platform. Recommended for businesses and individuals needing logistics solutions.",
            ],
            'whatsapp' => [
                'title' => "Just rated my LeSGo {$service->name} experience {$stars}! Great service. Try them: ",
                'description' => "",
            ],
            'telegram' => [
                'title' => "Rated my LeSGo {$service->name} experience {$stars}! 🚚 Reliable delivery service.",
                'description' => "",
            ],
        ];

        $template = $templates[$platform] ?? $templates['facebook'];
        
        return [
            'title' => $template['title'],
            'description' => $template['description'],
            'image_url' => $this->generateReviewImage($order, $rating),
            'share_url' => $this->generateShareUrl('review', $order->id),
            'metadata' => [
                'order_id' => $order->id,
                'rating' => $rating,
                'service_name' => $service->name,
            ],
        ];
    }

    /**
     * Generate share content for referral invitation.
     */
    public function generateReferralShare(User $user, string $platform): array
    {
        $referralCode = $user->referral_code ?? Str::upper(Str::random(8));
        
        $templates = [
            'facebook' => [
                'title' => "Join me on LeSGo and get ₱50 off your first order! 🎉",
                'description' => "I've been using LeSGo for all my delivery needs and it's amazing! Use my referral code {$referralCode} and get ₱50 off your first order. Fast, reliable, and professional service! #LeSGo #Referral #Delivery",
            ],
            'twitter' => [
                'title' => "Join me on @LeSGoPH and get ₱50 off! Use code {$referralCode} 🎉 #LeSGo #Referral #Delivery",
                'description' => "",
            ],
            'instagram' => [
                'title' => "LeSGo Referral: ₱50 Off! 🎉",
                'description' => "Hey friends! I've been using LeSGo for all my delivery needs and it's been amazing! 🚚✨\n\nJoin me and get ₱50 off your first order with my referral code: {$referralCode}\n\nFast, reliable, and professional service! 📦\n\n#LeSGo #Referral #Delivery #Philippines #Discount #FirstOrder",
            ],
            'linkedin' => [
                'title' => "LeSGo Delivery Platform Referral",
                'description' => "I've been using LeSGo for logistics and delivery services with great results. If you're looking for a reliable delivery platform, join using my referral code {$referralCode} and get ₱50 off your first order. Professional service and user-friendly platform.",
            ],
            'whatsapp' => [
                'title' => "Join LeSGo with my referral code {$referralCode} and get ₱50 off your first order! Fast and reliable delivery service: ",
                'description' => "",
            ],
            'telegram' => [
                'title' => "Join LeSGo with my referral code {$referralCode} and get ₱50 off! 🎉 Reliable delivery service.",
                'description' => "",
            ],
        ];

        $template = $templates[$platform] ?? $templates['facebook'];
        
        return [
            'title' => $template['title'],
            'description' => $template['description'],
            'image_url' => $this->generateReferralImage($user, $referralCode),
            'share_url' => $this->generateShareUrl('referral', $user->id, ['code' => $referralCode]),
            'metadata' => [
                'referral_code' => $referralCode,
                'user_name' => $user->name,
                'discount_amount' => 50,
            ],
        ];
    }

    /**
     * Generate share content for milestone achievement.
     */
    public function generateMilestoneShare(User $user, string $milestone, string $platform): array
    {
        $milestones = [
            'first_order' => 'Just completed my first LeSGo order! 🎉',
            '10_orders' => 'Reached 10 orders with LeSGo! 🏆',
            '50_orders' => 'Milestone: 50 orders completed with LeSGo! 🌟',
            '100_orders' => 'Century milestone: 100 orders with LeSGo! 💯',
            'loyal_customer' => 'Proud to be a loyal LeSGo customer! 👑',
            'top_reviewer' => 'Became a top reviewer on LeSGo! ⭐',
        ];

        $title = $milestones[$milestone] ?? 'Achieved a milestone with LeSGo! 🎉';
        
        $templates = [
            'facebook' => [
                'title' => $title,
                'description' => "LeSGo has been my go-to delivery service. Reliable, fast, and professional! #LeSGo #Milestone #Delivery #Philippines",
            ],
            'twitter' => [
                'title' => $title . " @LeSGoPH #LeSGo #Milestone #Delivery",
                'description' => "",
            ],
            'instagram' => [
                'title' => "LeSGo Milestone! 🎉",
                'description' => $title . "\n\nLeSGo has been my go-to delivery service. Reliable, fast, and professional! 🚚✨\n\n#LeSGo #Milestone #Delivery #Philippines #Achievement #CustomerJourney",
            ],
            'linkedin' => [
                'title' => "LeSGo Platform Milestone",
                'description' => $title . " LeSGo has consistently provided reliable delivery services. Great platform for both personal and business logistics needs.",
            ],
            'whatsapp' => [
                'title' => $title . " LeSGo is my trusted delivery partner. Check them out: ",
                'description' => "",
            ],
            'telegram' => [
                'title' => $title . " 🚚 LeSGo - reliable delivery service.",
                'description' => "",
            ],
        ];

        $template = $templates[$platform] ?? $templates['facebook'];
        
        return [
            'title' => $template['title'],
            'description' => $template['description'],
            'image_url' => $this->generateMilestoneImage($user, $milestone),
            'share_url' => $this->generateShareUrl('milestone', $user->id),
            'metadata' => [
                'milestone' => $milestone,
                'user_name' => $user->name,
                'achievement_date' => now()->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Create a social share record.
     */
    public function createShare(
        User $user,
        string $platform,
        string $shareType,
        array $content,
        ?Order $order = null
    ): SocialShare {
        return SocialShare::create([
            'user_id' => $user->id,
            'order_id' => $order?->id,
            'platform' => $platform,
            'share_type' => $shareType,
            'share_title' => $content['title'],
            'share_description' => $content['description'],
            'share_image_url' => $content['image_url'] ?? null,
            'share_url' => $content['share_url'],
            'share_metadata' => $content['metadata'] ?? [],
            'utm_source' => $platform,
            'utm_medium' => 'social',
            'utm_campaign' => $shareType,
            'shared_at' => now(),
        ]);
    }

    /**
     * Generate a shareable URL for tracking.
     */
    private function generateShareUrl(string $type, int $id, array $params = []): string
    {
        $baseUrl = config('app.url');
        $path = "/share/{$type}/{$id}";
        
        $queryParams = array_merge([
            'utm_source' => 'social_share',
            'utm_medium' => 'social',
            'utm_campaign' => $type,
        ], $params);
        
        return $baseUrl . $path . '?' . http_build_query($queryParams);
    }

    /**
     * Generate order completion image.
     */
    private function generateOrderCompletionImage(Order $order): ?string
    {
        // In a real implementation, you would generate a custom image
        // For now, return a placeholder or default image
        return config('app.url') . '/images/share/order-completed.png';
    }

    /**
     * Generate review image.
     */
    private function generateReviewImage(Order $order, int $rating): ?string
    {
        return config('app.url') . "/images/share/review-{$rating}-stars.png";
    }

    /**
     * Generate referral image.
     */
    private function generateReferralImage(User $user, string $code): ?string
    {
        return config('app.url') . '/images/share/referral-invite.png';
    }

    /**
     * Generate milestone image.
     */
    private function generateMilestoneImage(User $user, string $milestone): ?string
    {
        return config('app.url') . "/images/share/milestone-{$milestone}.png";
    }

    /**
     * Get platform-specific sharing guidelines.
     */
    public function getPlatformGuidelines(string $platform): array
    {
        $platforms = SocialShare::getSupportedPlatforms();
        $config = $platforms[$platform] ?? [];
        
        return [
            'platform' => $platform,
            'name' => $config['name'] ?? ucfirst($platform),
            'max_title_length' => $config['max_title_length'] ?? 100,
            'max_description_length' => $config['max_description_length'] ?? 500,
            'supports_images' => $config['supports_images'] ?? true,
            'optimal_image_size' => $this->getOptimalImageSize($platform),
            'best_practices' => $this->getBestPractices($platform),
        ];
    }

    /**
     * Get optimal image size for platform.
     */
    private function getOptimalImageSize(string $platform): array
    {
        $sizes = [
            'facebook' => ['width' => 1200, 'height' => 630],
            'twitter' => ['width' => 1200, 'height' => 675],
            'instagram' => ['width' => 1080, 'height' => 1080],
            'linkedin' => ['width' => 1200, 'height' => 627],
            'whatsapp' => ['width' => 400, 'height' => 400],
            'telegram' => ['width' => 400, 'height' => 400],
        ];

        return $sizes[$platform] ?? ['width' => 1200, 'height' => 630];
    }

    /**
     * Get best practices for platform.
     */
    private function getBestPractices(string $platform): array
    {
        $practices = [
            'facebook' => [
                'Use engaging visuals',
                'Include relevant hashtags (2-3)',
                'Ask questions to encourage engagement',
                'Post during peak hours (1-3 PM)',
            ],
            'twitter' => [
                'Keep it concise and punchy',
                'Use trending hashtags',
                'Include mentions (@LeSGoPH)',
                'Tweet during business hours',
            ],
            'instagram' => [
                'High-quality visuals are essential',
                'Use story highlights for important content',
                'Include 5-10 relevant hashtags',
                'Post consistently',
            ],
            'linkedin' => [
                'Professional tone and language',
                'Focus on business value',
                'Include industry insights',
                'Post during weekdays',
            ],
            'whatsapp' => [
                'Personal and direct messaging',
                'Keep messages concise',
                'Include clear call-to-action',
                'Respect privacy settings',
            ],
            'telegram' => [
                'Use channels for broadcasting',
                'Include multimedia content',
                'Engage with community',
                'Regular updates',
            ],
        ];

        return $practices[$platform] ?? [];
    }

    /**
     * Track share engagement.
     */
    public function trackEngagement(SocialShare $share, string $action, array $data = []): void
    {
        switch ($action) {
            case 'click':
                $share->incrementClicks();
                break;
            case 'view':
                $share->incrementViews();
                break;
            case 'update_engagement':
                $share->updateEngagement($data);
                break;
        }
    }

    /**
     * Get sharing analytics for user.
     */
    public function getUserSharingAnalytics(User $user): array
    {
        $shares = $user->socialShares()->with('order')->get();
        
        return [
            'total_shares' => $shares->count(),
            'total_engagement' => $shares->sum(fn($share) => $share->getTotalEngagement()),
            'platform_breakdown' => $shares->groupBy('platform')->map->count(),
            'share_type_breakdown' => $shares->groupBy('share_type')->map->count(),
            'top_performing_share' => $shares->sortByDesc(fn($share) => $share->getTotalEngagement())->first(),
            'recent_shares' => $shares->sortByDesc('shared_at')->take(5)->values(),
            'engagement_rate' => $shares->avg(fn($share) => $share->getEngagementRate()),
        ];
    }
}