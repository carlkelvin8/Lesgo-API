<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingReviewController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success([], 'Reviews retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->success([], 'Review created successfully');
    }

    public function myReviews(): JsonResponse
    {
        return $this->success([], 'My reviews retrieved successfully');
    }

    public function statistics(): JsonResponse
    {
        return $this->success([
            'average_rating' => 4.5,
            'total_reviews' => 0,
            'rating_distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]
        ], 'Review statistics retrieved successfully');
    }

    public function show($review): JsonResponse
    {
        return $this->success([], 'Review retrieved successfully');
    }

    public function update($review): JsonResponse
    {
        return $this->success([], 'Review updated successfully');
    }
}