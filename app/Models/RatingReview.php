<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingReview extends Model
{
    use HasFactory;

    protected $table = 'ratings_reviews';

    protected $fillable = [
        'user_id',
        'order_id',
        'driver_id',
        'service_id',
        'overall_rating',
        'service_rating',
        'driver_rating',
        'delivery_time_rating',
        'communication_rating',
        'professionalism_rating',
        'review_title',
        'review_comment',
        'review_tags',
        'review_images',
        'is_anonymous',
        'is_verified',
        'is_featured',
        'is_public',
        'status',
        'moderation_notes',
        'moderated_at',
        'moderated_by',
        'business_response',
        'business_responded_at',
    ];

    protected $casts = [
        'review_tags' => 'array',
        'review_images' => 'array',
        'is_anonymous' => 'boolean',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'is_public' => 'boolean',
        'moderated_at' => 'datetime',
        'business_responded_at' => 'datetime',
    ];

    /**
     * Get the user who wrote the review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order being reviewed.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the driver being reviewed.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the service being reviewed.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the moderator.
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * Scope for public reviews.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true)->where('status', 'approved');
    }

    /**
     * Scope for featured reviews.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for reviews by rating.
     */
    public function scopeByRating($query, int $rating)
    {
        return $query->where('overall_rating', $rating);
    }

    /**
     * Get average rating breakdown.
     */
    public static function getAverageRatings($driverId = null, $serviceId = null): array
    {
        $query = static::where('status', 'approved');
        
        if ($driverId) {
            $query->where('driver_id', $driverId);
        }
        
        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        return [
            'overall_rating' => round($query->avg('overall_rating'), 2),
            'service_rating' => round($query->avg('service_rating'), 2),
            'driver_rating' => round($query->avg('driver_rating'), 2),
            'delivery_time_rating' => round($query->avg('delivery_time_rating'), 2),
            'communication_rating' => round($query->avg('communication_rating'), 2),
            'professionalism_rating' => round($query->avg('professionalism_rating'), 2),
            'total_reviews' => $query->count(),
        ];
    }

    /**
     * Get rating distribution.
     */
    public static function getRatingDistribution($driverId = null, $serviceId = null): array
    {
        $query = static::where('status', 'approved');
        
        if ($driverId) {
            $query->where('driver_id', $driverId);
        }
        
        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = $query->where('overall_rating', $i)->count();
        }

        return $distribution;
    }

    /**
     * Check if review can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->created_at->diffInHours(now()) <= 24 && $this->status === 'approved';
    }

    /**
     * Get display name for anonymous reviews.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return 'Anonymous Customer';
        }

        return $this->user->name ?? 'Customer';
    }
}