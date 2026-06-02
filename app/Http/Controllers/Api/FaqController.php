<?php

namespace App\Http\Controllers\Api;

use App\Models\FaqArticle;
use App\Models\FaqCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Get FAQ categories.
     */
    public function categories(): JsonResponse
    {
        $categories = FaqCategory::withCount('articles')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($categories, 'FAQ categories retrieved successfully');
    }

    /**
     * Get articles in a category.
     */
    public function categoryArticles($categoryId): JsonResponse
    {
        $category = FaqCategory::where('id', $categoryId)
            ->where('is_active', true)
            ->first();

        if (!$category) {
            return $this->error('FAQ category not found.', 404);
        }

        $articles = FaqArticle::where('category_id', $categoryId)
            ->where('is_published', true)
            ->with('category:id,name,slug')
            ->orderByDesc('view_count')
            ->get();

        return $this->success($articles, 'Category articles retrieved successfully');
    }

    /**
     * Get a specific FAQ article.
     */
    public function article($articleId): JsonResponse
    {
        $article = FaqArticle::with(['category:id,name,slug'])
            ->where('id', $articleId)
            ->where('is_published', true)
            ->first();

        if (!$article) {
            return $this->error('FAQ article not found.', 404);
        }

        // Increment view count
        $article->increment('view_count');

        return $this->success($article, 'FAQ article retrieved successfully');
    }

    /**
     * Search FAQ articles.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:faq_categories,id',
        ]);

        $query = FaqArticle::where('is_published', true)
            ->with('category:id,name,slug');

        // Search in title and content
        $searchTerm = $validated['q'];
        $query->where(function ($q) use ($searchTerm) {
            $q->where('title', 'like', '%' . $searchTerm . '%')
              ->orWhere('content', 'like', '%' . $searchTerm . '%')
              ->orWhere('tags', 'like', '%' . $searchTerm . '%');
        });

        // Filter by category
        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        $articles = $query->orderByDesc('view_count')
            ->limit(20)
            ->get();

        return $this->success($articles, 'FAQ search results retrieved successfully');
    }

    /**
     * Get featured articles.
     */
    public function featured(): JsonResponse
    {
        $articles = FaqArticle::where('is_published', true)
            ->where('is_featured', true)
            ->with('category:id,name,slug')
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        return $this->success($articles, 'Featured articles retrieved successfully');
    }

    /**
     * Flat FAQ article list for mobile app (maps to Flutter FaqArticleDto).
     */
    public function articles(): JsonResponse
    {
        $this->ensureDefaultFaqs();

        $articles = FaqArticle::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (FaqArticle $article) => $this->formatArticleForMobile($article))
            ->values();

        return $this->success($articles, 'FAQ articles retrieved successfully');
    }

    private function formatArticleForMobile(FaqArticle $article): array
    {
        return [
            'id' => $article->id,
            'category_id' => $article->category_id,
            'title' => $article->title,
            'content' => $article->content,
            'display_order' => $article->sort_order ?? 0,
            'is_featured' => (bool) $article->is_featured,
            'is_active' => (bool) $article->is_published,
            'view_count' => (int) $article->view_count,
            'helpful_count' => (int) $article->helpful_count,
            'not_helpful_count' => (int) $article->not_helpful_count,
            'created_at' => optional($article->created_at)->toISOString(),
            'updated_at' => optional($article->updated_at)->toISOString(),
        ];
    }

    private function ensureDefaultFaqs(): void
    {
        if (FaqArticle::where('is_published', true)->exists()) {
            return;
        }

        $category = FaqCategory::firstOrCreate(
            ['slug' => 'general'],
            [
                'name' => 'General',
                'description' => 'General questions about LesGo services',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $defaults = [
            [
                'title' => 'How much are delivery rates?',
                'content' => 'Delivery fees depend on distance and service type. LesGo, LesRide, and LesEat show the estimated fee before you confirm your order.',
                'sort_order' => 1,
            ],
            [
                'title' => 'Where do you deliver?',
                'content' => 'LesGo currently serves Cagayan de Oro and nearby areas within our service radius.',
                'sort_order' => 2,
            ],
            [
                'title' => 'What is LesGo?',
                'content' => 'LesGo is our on-demand delivery service for parcels, documents, and items you need picked up and dropped off around the city.',
                'sort_order' => 3,
            ],
            [
                'title' => 'What is LesBuy?',
                'content' => 'LesBuy lets you order items from partner stores or submit a manual shopping list for rider purchase and delivery.',
                'sort_order' => 4,
            ],
            [
                'title' => 'What is LesEat?',
                'content' => 'LesEat is our food delivery service. Browse partner restaurants, add items to your cart, and track your order until it arrives.',
                'sort_order' => 5,
            ],
        ];

        foreach ($defaults as $item) {
            FaqArticle::firstOrCreate(
                [
                    'category_id' => $category->id,
                    'title' => $item['title'],
                ],
                [
                    'content' => $item['content'],
                    'sort_order' => $item['sort_order'],
                    'is_published' => true,
                    'is_featured' => $item['sort_order'] <= 2,
                ]
            );
        }
    }

    /**
     * Get popular articles.
     */
    public function popular(): JsonResponse
    {
        $articles = FaqArticle::where('is_published', true)
            ->with('category:id,name,slug')
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        return $this->success($articles, 'Popular articles retrieved successfully');
    }

    /**
     * Get FAQ statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalArticles = \App\Models\FaqArticle::where('is_published', true)->count();
            $totalViews = \App\Models\FaqArticle::where('is_published', true)->sum('view_count');
            $helpfulVotes = \App\Models\FaqArticle::where('is_published', true)->sum('helpful_count');
            $notHelpfulVotes = \App\Models\FaqArticle::where('is_published', true)->sum('not_helpful_count');

            $categories = \App\Models\FaqCategory::withCount(['articles' => function ($query) {
                $query->where('is_published', true);
            }])
                ->where('is_active', true)
                ->get();

            $mostViewedCategory = \App\Models\FaqCategory::withCount(['articles' => function ($query) {
                $query->where('is_published', true);
            }])
                ->withSum(['articles' => function ($query) {
                    $query->where('is_published', true);
                }], 'view_count')
                ->orderByDesc('articles_sum_view_count')
                ->first();

            return $this->success([
                'total_articles' => $totalArticles,
                'total_views' => $totalViews,
                'helpful_votes' => $helpfulVotes,
                'not_helpful_votes' => $notHelpfulVotes,
                'helpful_percentage' => ($helpfulVotes + $notHelpfulVotes) > 0 
                    ? round(($helpfulVotes / ($helpfulVotes + $notHelpfulVotes)) * 100, 2) 
                    : 0,
                'categories' => $categories,
                'most_viewed_category' => $mostViewedCategory ? $mostViewedCategory->name : null,
            ], 'FAQ statistics retrieved successfully');
        } catch (\Exception $e) {
            // Return zeros if tables don't exist or queries fail
            return $this->success([
                'total_articles' => 0,
                'total_views' => 0,
                'helpful_votes' => 0,
                'not_helpful_votes' => 0,
                'helpful_percentage' => 0,
                'categories' => [],
                'most_viewed_category' => null,
            ], 'FAQ statistics retrieved successfully');
        }
    }

    /**
     * Mark article as helpful.
     */
    public function markHelpful($articleId): JsonResponse
    {
        $article = FaqArticle::where('id', $articleId)
            ->where('is_published', true)
            ->first();

        if (!$article) {
            return $this->error('FAQ article not found.', 404);
        }

        $article->increment('helpful_count');

        return $this->success([
            'article_id' => $article->id,
            'helpful_votes' => $article->helpful_count,
        ], 'Article marked as helpful');
    }

    /**
     * Mark article as not helpful.
     */
    public function markNotHelpful($articleId): JsonResponse
    {
        $article = FaqArticle::where('id', $articleId)
            ->where('is_published', true)
            ->first();

        if (!$article) {
            return $this->error('FAQ article not found.', 404);
        }

        $article->increment('not_helpful_count');

        return $this->success([
            'article_id' => $article->id,
            'not_helpful_votes' => $article->not_helpful_count,
        ], 'Article marked as not helpful');
    }
}