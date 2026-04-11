<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function categories(): JsonResponse
    {
        return $this->success([
            ['id' => 1, 'name' => 'General', 'article_count' => 0],
            ['id' => 2, 'name' => 'Orders', 'article_count' => 0],
            ['id' => 3, 'name' => 'Payments', 'article_count' => 0]
        ], 'FAQ categories retrieved successfully');
    }

    public function categoryArticles($category): JsonResponse
    {
        return $this->success([], 'Category articles retrieved successfully');
    }

    public function article($article): JsonResponse
    {
        return $this->success([], 'FAQ article retrieved successfully');
    }

    public function search(): JsonResponse
    {
        return $this->success([], 'FAQ search results retrieved successfully');
    }

    public function featured(): JsonResponse
    {
        return $this->success([], 'Featured articles retrieved successfully');
    }

    public function popular(): JsonResponse
    {
        return $this->success([], 'Popular articles retrieved successfully');
    }

    public function statistics(): JsonResponse
    {
        return $this->success([
            'total_articles' => 0,
            'total_views' => 0,
            'helpful_votes' => 0
        ], 'FAQ statistics retrieved successfully');
    }

    public function markHelpful($article): JsonResponse
    {
        return $this->success([], 'Article marked as helpful');
    }

    public function markNotHelpful($article): JsonResponse
    {
        return $this->success([], 'Article marked as not helpful');
    }
}