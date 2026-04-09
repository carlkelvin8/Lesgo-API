<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use App\Models\FaqArticle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    /**
     * Get all FAQ categories with their articles.
     */
    public function categories(): JsonResponse
    {
        $categories = FaqCategory::active()
            ->ordered()
            ->with(['publishedArticles' => function ($query) {
                $query->ordered()->select('id', 'category_id', 'title', 'slug', 'excerpt', 'view_count', 'helpful_count', 'not_helpful_count', 'is_featured');
            }])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'FAQ categories retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $categories,
        ]);
    }

    /**
     * Get articles in a specific category.
     */
    public function categoryArticles(FaqCategory $category, Request $request): JsonResponse
    {
        if (!$category->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 404);
        }

        $articles = $category->publishedArticles()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Category articles retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'category' => $category,
                'articles' => $articles->items(),
            ],
            'meta' => [
                'total' => $articles->total(),
                'per_page' => $articles->perPage(),
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'from' => $articles->firstItem(),
                'to' => $articles->lastItem(),
                'has_more' => $articles->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get a specific FAQ article.
     */
    public function article(FaqArticle $article): JsonResponse
    {
        if (!$article->is_published) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 404);
        }

        // Increment view count
        $article->incrementViews();

        $article->load(['category', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'FAQ article retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $article,
        ]);
    }

    /**
     * Search FAQ articles.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'category_id' => 'nullable|exists:faq_categories,id',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = FaqArticle::published()
            ->with(['category'])
            ->search($request->q);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $articles = $query->orderByRaw('
            CASE 
                WHEN title LIKE ? THEN 1
                WHEN title LIKE ? THEN 2
                WHEN excerpt LIKE ? THEN 3
                ELSE 4
            END, view_count DESC
        ', [
            '%' . $request->q . '%',
            $request->q . '%',
            '%' . $request->q . '%'
        ])->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Search results retrieved successfully',
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'data' => [
                'query' => $request->q,
                'results' => $articles->items(),
            ],
            'meta' => [
                'total' => $articles->total(),
                'per_page' => $articles->perPage(),
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'from' => $articles->firstItem(),
                'to' => $articles->lastItem(),
                'has_more' => $articles->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get featured FAQ articles.
     */
    public function featured(): JsonResponse
    {
        $articles = FaqArticle::published()
            ->featured()
            ->with(['category'])
            ->ordered()
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Featured articles retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $articles,
        ]);
    }

    /**
     * Get popular FAQ articles.
     */
    public function popular(): JsonResponse
    {
        $articles = FaqArticle::published()
            ->with(['category'])
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Popular articles retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $articles,
        ]);
    }

    /**
     * Mark article as helpful.
     */
    public function markHelpful(FaqArticle $article): JsonResponse
    {
        if (!$article->is_published) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 404);
        }

        $article->markAsHelpful();

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => [
                'helpful_count' => $article->fresh()->helpful_count,
                'helpfulness_ratio' => $article->fresh()->helpfulness_ratio,
            ],
        ]);
    }

    /**
     * Mark article as not helpful.
     */
    public function markNotHelpful(FaqArticle $article): JsonResponse
    {
        if (!$article->is_published) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found',
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ], 404);
        }

        $article->markAsNotHelpful();

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => [
                'not_helpful_count' => $article->fresh()->not_helpful_count,
                'helpfulness_ratio' => $article->fresh()->helpfulness_ratio,
            ],
        ]);
    }

    /**
     * Get FAQ statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_categories' => FaqCategory::active()->count(),
            'total_articles' => FaqArticle::published()->count(),
            'total_views' => FaqArticle::published()->sum('view_count'),
            'most_viewed_articles' => FaqArticle::published()
                ->with(['category'])
                ->orderBy('view_count', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'slug', 'category_id', 'view_count']),
            'most_helpful_articles' => FaqArticle::published()
                ->with(['category'])
                ->orderBy('helpful_count', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'slug', 'category_id', 'helpful_count', 'not_helpful_count']),
        ];

        return response()->json([
            'success' => true,
            'message' => 'FAQ statistics retrieved successfully',
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'data' => $stats,
        ]);
    }
}