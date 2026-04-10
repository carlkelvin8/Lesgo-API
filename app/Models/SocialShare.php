<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'platform',
        'share_type',
        'share_url',
        'external_post_id',
        'share_title',
        'share_description',
        'share_image_url',
        'share_metadata',
        'clicks',
        'views',
        'likes',
        'shares',
        'comments',
        'is_public',
        'is_active',
        'shared_at',
        'expires_at',
        'analytics_data',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected $casts = [
        'share_metadata' => 'array',
        'analytics_data' => 'array',
        'shared_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeForShareType($query, string $shareType)
    {
        return $query->where('share_type', $shareType);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('shared_at', '>=', now()->subDays($days));
    }

    public function scopePopular($query, int $minEngagement = 10)
    {
        return $query->whereRaw('(clicks + views + likes + shares + comments) >= ?', [$minEngagement]);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
        });
    }

    // ── Helper Methods ───────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getTotalEngagement(): int
    {
        return $this->clicks + $this->views + $this->likes + $this->shares + $this->comments;
    }

    public function getEngagementRate(): float
    {
        if ($this->views === 0) {
            return 0;
        }

        $engagements = $this->clicks + $this->likes + $this->shares + $this->comments;
        return round(($engagements / $this->views) * 100, 2);
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function updateEngagement(array $data): void
    {
        $this->update([
            'likes' => $data['likes'] ?? $this->likes,
            'shares' => $data['shares'] ?? $this->shares,
            'comments' => $data['comments'] ?? $this->comments,
            'views' => $data['views'] ?? $this->views,
        ]);
    }

    // ── Static Methods ───────────────────────────────────────────────────

    public static function getSupportedPlatforms(): array
    {
        return [
            'facebook' => [
                'name' => 'Facebook',
                'icon' => 'fab fa-facebook',
                'color' => '#1877F2',
                'share_url_template' => 'https://www.facebook.com/sharer/sharer.php?u={url}&quote={title}',
                'supports_images' => true,
                'max_title_length' => 100,
                'max_description_length' => 500,
            ],
            'twitter' => [
                'name' => 'Twitter/X',
                'icon' => 'fab fa-twitter',
                'color' => '#1DA1F2',
                'share_url_template' => 'https://twitter.com/intent/tweet?text={title}&url={url}&hashtags={hashtags}',
                'supports_images' => true,
                'max_title_length' => 280,
                'max_description_length' => 280,
            ],
            'instagram' => [
                'name' => 'Instagram',
                'icon' => 'fab fa-instagram',
                'color' => '#E4405F',
                'share_url_template' => null, // Instagram doesn't support direct URL sharing
                'supports_images' => true,
                'max_title_length' => 150,
                'max_description_length' => 2200,
            ],
            'linkedin' => [
                'name' => 'LinkedIn',
                'icon' => 'fab fa-linkedin',
                'color' => '#0A66C2',
                'share_url_template' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}&title={title}&summary={description}',
                'supports_images' => true,
                'max_title_length' => 150,
                'max_description_length' => 700,
            ],
            'whatsapp' => [
                'name' => 'WhatsApp',
                'icon' => 'fab fa-whatsapp',
                'color' => '#25D366',
                'share_url_template' => 'https://wa.me/?text={title}%20{url}',
                'supports_images' => false,
                'max_title_length' => 1000,
                'max_description_length' => 1000,
            ],
            'telegram' => [
                'name' => 'Telegram',
                'icon' => 'fab fa-telegram',
                'color' => '#0088CC',
                'share_url_template' => 'https://t.me/share/url?url={url}&text={title}',
                'supports_images' => false,
                'max_title_length' => 1000,
                'max_description_length' => 1000,
            ],
        ];
    }

    public static function getShareTypes(): array
    {
        return [
            'order_completed' => 'Order Completed',
            'service_review' => 'Service Review',
            'milestone' => 'Milestone Achievement',
            'achievement' => 'Personal Achievement',
            'referral' => 'Referral Invitation',
            'promotion' => 'Promotion/Discount',
            'experience' => 'Service Experience',
        ];
    }

    public function getPlatformConfig(): array
    {
        return self::getSupportedPlatforms()[$this->platform] ?? [];
    }

    public function getShareTypeLabel(): string
    {
        return self::getShareTypes()[$this->share_type] ?? 'Unknown';
    }

    public function generateShareUrl(): string
    {
        $config = $this->getPlatformConfig();
        $template = $config['share_url_template'] ?? null;

        if (!$template) {
            return $this->share_url ?? '';
        }

        $replacements = [
            '{url}' => urlencode($this->share_url ?? ''),
            '{title}' => urlencode($this->share_title),
            '{description}' => urlencode($this->share_description),
            '{hashtags}' => urlencode($this->getHashtags()),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function getHashtags(): string
    {
        $hashtags = ['LeSGo', 'Delivery', 'Philippines'];
        
        if ($this->share_type === 'order_completed') {
            $hashtags[] = 'OrderCompleted';
        } elseif ($this->share_type === 'service_review') {
            $hashtags[] = 'ServiceReview';
        }

        return implode(',', $hashtags);
    }

    public function getOpenGraphData(): array
    {
        return [
            'og:title' => $this->share_title,
            'og:description' => $this->share_description,
            'og:image' => $this->share_image_url,
            'og:url' => $this->share_url,
            'og:type' => 'website',
            'og:site_name' => 'LeSGo',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $this->share_title,
            'twitter:description' => $this->share_description,
            'twitter:image' => $this->share_image_url,
        ];
    }

    // ── Analytics Methods ─────────────────────────────────────────────────

    public static function getTopPerformingShares(int $limit = 10)
    {
        return self::with(['user', 'order'])
            ->selectRaw('*, (clicks + views + likes + shares + comments) as total_engagement')
            ->orderBy('total_engagement', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function getPlatformStats()
    {
        return self::selectRaw('
            platform,
            COUNT(*) as total_shares,
            SUM(clicks) as total_clicks,
            SUM(views) as total_views,
            SUM(likes) as total_likes,
            SUM(shares) as total_shares_count,
            SUM(comments) as total_comments,
            AVG(clicks + views + likes + shares + comments) as avg_engagement
        ')
        ->groupBy('platform')
        ->get();
    }

    public static function getShareTypeStats()
    {
        return self::selectRaw('
            share_type,
            COUNT(*) as total_shares,
            SUM(clicks + views + likes + shares + comments) as total_engagement,
            AVG(clicks + views + likes + shares + comments) as avg_engagement
        ')
        ->groupBy('share_type')
        ->get();
    }
}