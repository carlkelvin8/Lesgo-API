<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Partner;
use App\Models\RatingReview;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingReviewController extends Controller
{
    /**
     * List reviews with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
            'rating' => 'nullable|integer|min:1|max:5',
            'status' => 'nullable|in:pending,approved,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = RatingReview::with([
            'user:id,name,email,profile_photo_url',
            'order:id,status,created_at',
            'driver:id,name,profile_photo_url',
            'service:id,name,code',
        ]);

        // Filter by driver
        if (!empty($validated['driver_id'])) {
            $query->where('driver_id', $validated['driver_id']);
        }

        // Filter by service
        if (!empty($validated['service_id'])) {
            $query->where('service_id', $validated['service_id']);
        }

        // Filter by rating
        if (!empty($validated['rating'])) {
            $query->where('overall_rating', $validated['rating']);
        }

        // Filter by status (admins can see all, users only see approved)
        $user = $request->user();
        if (!empty($validated['status']) && $user && $user->isAdmin()) {
            $query->where('status', $validated['status']);
        } else {
            $query->where('status', 'approved');
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $reviews = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success($reviews, 'Reviews retrieved successfully');
    }

    /**
     * List approved reviews for a partner (via completed orders).
     */
    public function partnerReviews(Request $request, Partner $partner): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $reviews = RatingReview::with([
            'user:id,name,email,profile_photo_url',
            'order:id,status,created_at,partner_id',
            'driver:id,name,profile_photo_url',
            'service:id,name,code',
        ])
            ->where('status', 'approved')
            ->whereHas('order', function ($query) use ($partner) {
                $query->where('partner_id', $partner->id);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->success($reviews, 'Partner reviews retrieved successfully');
    }

    /**
     * Create a new review.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'driver_id' => 'required|integer|exists:users,id',
            'service_id' => 'required|integer|exists:services,id',
            'overall_rating' => 'required|integer|min:1|max:5',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'driver_rating' => 'nullable|integer|min:1|max:5',
            'delivery_time_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'professionalism_rating' => 'nullable|integer|min:1|max:5',
            'review_title' => 'nullable|string|max:255',
            'review_comment' => 'nullable|string|max:2000',
            'review_tags' => 'nullable|array',
            'review_images' => 'nullable|array',
            'is_anonymous' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // Check if user has already reviewed this order
        $existingReview = RatingReview::where('user_id', $user->id)
            ->where('order_id', $validated['order_id'])
            ->first();

        if ($existingReview) {
            return $this->error('You have already reviewed this order.', 409);
        }

        // Verify the user owns this order
        $order = Order::find($validated['order_id']);
        if ((int) $order->customer_id !== (int) $user->id) {
            return $this->error('You can only review your own orders.', 403);
        }

        // Verify order is completed
        if ($order->status !== 'completed') {
            return $this->error('You can only review completed orders.', 400);
        }

        // Set default values
        $validated['user_id'] = $user->id;
        $validated['status'] = 'approved';
        $validated['is_public'] = $validated['is_public'] ?? true;
        $validated['is_anonymous'] = $validated['is_anonymous'] ?? false;
        $validated['is_verified'] = true;

        $review = RatingReview::create($validated);

        // Update driver's average rating
        $this->updateDriverRating($validated['driver_id']);

        $review->load([
            'user:id,name,email,profile_photo_url',
            'driver:id,name,profile_photo_url',
            'service:id,name,code',
        ]);

        return $this->created($review, 'Review created successfully');
    }

    /**
     * Get current user's reviews.
     */
    public function myReviews(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $reviews = RatingReview::where('user_id', $user->id)
            ->with([
                'order:id,status,created_at',
                'driver:id,name,profile_photo_url',
                'service:id,name,code',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->success($reviews, 'My reviews retrieved successfully');
    }

    /**
     * Get review statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $driverId = $request->input('driver_id');
        $serviceId = $request->input('service_id');

        $averages = RatingReview::getAverageRatings($driverId, $serviceId);
        $distribution = RatingReview::getRatingDistribution($driverId, $serviceId);

        return $this->success([
            'average_rating' => $averages['overall_rating'],
            'total_reviews' => $averages['total_reviews'],
            'rating_breakdown' => [
                'overall' => $averages['overall_rating'],
                'service' => $averages['service_rating'],
                'driver' => $averages['driver_rating'],
                'delivery_time' => $averages['delivery_time_rating'],
                'communication' => $averages['communication_rating'],
                'professionalism' => $averages['professionalism_rating'],
            ],
            'rating_distribution' => $distribution,
        ], 'Review statistics retrieved successfully');
    }

    /**
     * Get a specific review.
     */
    public function show(Request $request, $reviewId): JsonResponse
    {
        $review = RatingReview::with([
            'user:id,name,email,profile_photo_url',
            'order:id,status,created_at',
            'driver:id,name,profile_photo_url',
            'service:id,name,code',
        ])->find($reviewId);

        if (!$review) {
            return $this->error('Review not found.', 404);
        }

        // Check access permissions
        $user = $request->user();
        if ($review->status !== 'approved' && (!$user || !$user->isAdmin())) {
            return $this->error('Review not available.', 403);
        }

        return $this->success($review, 'Review retrieved successfully');
    }

    /**
     * Update a review (within 24 hours).
     */
    public function update(Request $request, $reviewId): JsonResponse
    {
        $user = $request->user();

        $review = RatingReview::where('user_id', $user->id)->find($reviewId);

        if (!$review) {
            return $this->error('Review not found.', 404);
        }

        // Check if review can be edited (within 24 hours)
        if (!$review->canBeEdited()) {
            return $this->error('Reviews can only be edited within 24 hours of creation.', 403);
        }

        $validated = $request->validate([
            'overall_rating' => 'nullable|integer|min:1|max:5',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'driver_rating' => 'nullable|integer|min:1|max:5',
            'delivery_time_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'professionalism_rating' => 'nullable|integer|min:1|max:5',
            'review_title' => 'nullable|string|max:255',
            'review_comment' => 'nullable|string|max:2000',
            'review_tags' => 'nullable|array',
            'review_images' => 'nullable|array',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $review->update($validated);

        // Update driver rating if driver rating changed
        if (isset($validated['driver_rating']) || isset($validated['overall_rating'])) {
            $this->updateDriverRating($review->driver_id);
        }

        $review->load([
            'user:id,name,email,profile_photo_url',
            'driver:id,name,profile_photo_url',
            'service:id,name,code',
        ]);

        return $this->success($review, 'Review updated successfully');
    }

    /**
     * Update driver's average rating.
     */
    private function updateDriverRating(int $driverId): void
    {
        $driver = \App\Models\User::find($driverId);
        if ($driver && $driver->driverProfile) {
            $averages = RatingReview::getAverageRatings($driverId);
            $driver->driverProfile->update([
                'rating' => $averages['overall_rating'] ?? 0,
            ]);
        }
    }
}