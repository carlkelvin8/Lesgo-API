<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RatingReview;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class RatingReviewController extends Controller
{
    /**
     * Get reviews for a specific driver or service.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'nullable|exists:users,id',
            'service_id' => 'nullable|exists:services,id',
            'rating' => 'nullable|integer|min:1|max:5',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = RatingReview::with(['user', 'order', 'driver', 'service'])
            ->public()
            ->orderBy('created_at', 'desc');

        if ($request->driver_id) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->service_id) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->rating) {
            $query->where('overall_rating', $request->rating);
        }

        $reviews = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $reviews->items(),
            'meta' => [
                'total' => $reviews->total(),
                'per_page' => $reviews->perPage(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'from' => $reviews->firstItem(),
                'to' => $reviews->lastItem(),
                'has_more' => $reviews->hasMorePages(),
            ],
            'links' => [
                'first' => $reviews->url(1),
                'last' => $reviews->url($reviews->lastPage()),
                'prev' => $reviews->previousPageUrl(),
                'next' => $reviews->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Create a new review.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'overall_rating' => 'required|integer|min:1|max:5',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'driver_rating' => 'nullable|integer|min:1|max:5',
            'delivery_time_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'professionalism_rating' => 'nullable|integer|min:1|max:5',
            'review_title' => 'nullable|string|max:255',
            'review_comment' => 'nullable|string|max:1000',
            'review_tags' => 'nullable|array',
            'review_tags.*' => 'string|max:50',
            'review_images' => 'nullable|array|max:5',
            'review_images.*' => 'url',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Check if user can review this order
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review your own orders',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        // Check if order is completed
        if ($order->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'You can only review completed orders',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        // Check if review already exists
        $existingReview = RatingReview::where('user_id', auth()->id())
            ->where('order_id', $request->order_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this order',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $review = RatingReview::create([
            'user_id' => auth()->id(),
            'order_id' => $request->order_id,
            'driver_id' => $order->driver_id,
            'service_id' => $order->service_id,
            'overall_rating' => $request->overall_rating,
            'service_rating' => $request->service_rating,
            'driver_rating' => $request->driver_rating,
            'delivery_time_rating' => $request->delivery_time_rating,
            'communication_rating' => $request->communication_rating,
            'professionalism_rating' => $request->professionalism_rating,
            'review_title' => $request->review_title,
            'review_comment' => $request->review_comment,
            'review_tags' => $request->review_tags,
            'review_images' => $request->review_images,
            'is_anonymous' => $request->is_anonymous ?? false,
            'is_verified' => true,
            'status' => 'approved',
        ]);

        $review->load(['user', 'order', 'driver', 'service']);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $review,
        ], 201);
    }

    /**
     * Get a specific review.
     */
    public function show(RatingReview $review): JsonResponse
    {
        if (!$review->is_public || $review->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 404);
        }

        $review->load(['user', 'order', 'driver', 'service']);

        return response()->json([
            'success' => true,
            'message' => 'Review retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $review,
        ]);
    }

    /**
     * Update a review (within 24 hours).
     */
    public function update(Request $request, RatingReview $review): JsonResponse
    {
        if ($review->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own reviews',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 403);
        }

        if (!$review->canBeEdited()) {
            return response()->json([
                'success' => false,
                'message' => 'Review can only be edited within 24 hours of creation',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 400);
        }

        $request->validate([
            'overall_rating' => 'required|integer|min:1|max:5',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'driver_rating' => 'nullable|integer|min:1|max:5',
            'delivery_time_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'professionalism_rating' => 'nullable|integer|min:1|max:5',
            'review_title' => 'nullable|string|max:255',
            'review_comment' => 'nullable|string|max:1000',
            'review_tags' => 'nullable|array',
            'review_tags.*' => 'string|max:50',
            'review_images' => 'nullable|array|max:5',
            'review_images.*' => 'url',
        ]);

        $review->update($request->only([
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
        ]));

        $review->load(['user', 'order', 'driver', 'service']);

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $review,
        ]);
    }

    /**
     * Get rating statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'nullable|exists:users,id',
            'service_id' => 'nullable|exists:services,id',
        ]);

        $averageRatings = RatingReview::getAverageRatings(
            $request->driver_id,
            $request->service_id
        );

        $ratingDistribution = RatingReview::getRatingDistribution(
            $request->driver_id,
            $request->service_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Rating statistics retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'average_ratings' => $averageRatings,
                'rating_distribution' => $ratingDistribution,
            ],
        ]);
    }

    /**
     * Get user's reviews.
     */
    public function myReviews(Request $request): JsonResponse
    {
        $reviews = RatingReview::with(['order', 'driver', 'service'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Your reviews retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => $reviews->items(),
            'meta' => [
                'total' => $reviews->total(),
                'per_page' => $reviews->perPage(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'from' => $reviews->firstItem(),
                'to' => $reviews->lastItem(),
                'has_more' => $reviews->hasMorePages(),
            ],
        ]);
    }
}