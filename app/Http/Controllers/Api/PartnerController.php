<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Partner;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/partners",
     *     summary="List partners (restaurants, stores, etc.)",
     *     description="Returns paginated list of partners. Filter by category, status, featured, or search by name.",
     *     tags={"Partners"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="category", in="query", required=false, description="e.g. restaurant, grocery, pharmacy, bakery", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"active","inactive","suspended"})),
     *     @OA\Parameter(name="is_open", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="is_featured", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search by name or description", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Paginated list of partners",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Jollibee"),
     *                 @OA\Property(property="logo_url", type="string", nullable=true),
     *                 @OA\Property(property="cover_image_url", type="string", nullable=true),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="category", type="string", example="restaurant"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="cuisine_types", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="rating", type="number", example=4.5),
     *                 @OA\Property(property="total_reviews", type="integer", example=120),
     *                 @OA\Property(property="delivery_fee", type="number", example=49.00),
     *                 @OA\Property(property="min_order_amount", type="integer", example=150),
     *                 @OA\Property(property="estimated_delivery_minutes", type="integer", example=30),
     *                 @OA\Property(property="is_open", type="boolean", example=true),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="active")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()->where('status', 'active');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_open')) {
            $query->where('is_open', $request->boolean('is_open'));
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Admin can see all statuses
        if ($request->user()?->isAdmin() && $request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 20);

        $partners = $query
            ->with('branches') // Eager load branches for address info
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->orderBy('name')
            ->paginate($perPage);

        return $this->success($partners);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/partners",
     *     summary="Create a partner (admin only)",
     *     tags={"Partners"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"name"},
     *             @OA\Property(property="name", type="string", example="Jollibee"),
     *             @OA\Property(property="logo_url", type="string", nullable=true),
     *             @OA\Property(property="cover_image_url", type="string", nullable=true),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="category", type="string", example="restaurant"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"fast food","chicken"}),
     *             @OA\Property(property="cuisine_types", type="array", @OA\Items(type="string"), example={"Filipino","American"}),
     *             @OA\Property(property="delivery_fee", type="number", example=49.00),
     *             @OA\Property(property="min_order_amount", type="integer", example=150),
     *             @OA\Property(property="estimated_delivery_minutes", type="integer", example=30),
     *             @OA\Property(property="is_open", type="boolean", example=true),
     *             @OA\Property(property="is_featured", type="boolean", example=false),
     *             @OA\Property(property="accepts_online_payment", type="boolean", example=true),
     *             @OA\Property(property="opening_hours", type="object",
     *                 example={"mon":{"open":"08:00","close":"22:00"},"tue":{"open":"08:00","close":"22:00"}}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Partner created")
     * )
     */
    public function store(StorePartnerRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Auto-generate slug from name if not provided
        if (empty($data['slug'])) {
            $base = \Illuminate\Support\Str::slug($data['name']);
            $slug = $base;
            $i = 1;
            while (Partner::where('slug', $slug)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            $data['slug'] = $slug;
        }

        $partner = Partner::create($data);

        return $this->created($partner, 'Partner created successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/partners/{id}",
     *     summary="Get partner details with branches",
     *     tags={"Partners"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Partner details including branches"),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function show(Partner $partner): JsonResponse
    {
        $partner->load([
            'branches' => fn ($q) => $q->orderByDesc('is_primary'),
        ]);

        return $this->success($partner);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/partners/{id}",
     *     summary="Update a partner",
     *     tags={"Partners"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="is_open", type="boolean"),
     *         @OA\Property(property="status", type="string", enum={"active","inactive","suspended"})
     *     )),
     *     @OA\Response(response=200, description="Partner updated")
     * )
     */
    public function update(UpdatePartnerRequest $request, Partner $partner): JsonResponse
    {
        $partner->update($request->validated());

        return $this->success($partner, 'Partner updated successfully');
    }

    /**
     * GET /api/v1/partners/{id}/menu
     * Get menu categories and items for a partner/restaurant.
     */
    public function menu(Partner $partner): JsonResponse
    {
        try {
            $categories = MenuCategory::where('partner_id', $partner->id)
                ->where('is_active', true)
                ->with(['availableItems' => function ($q) {
                    $q->orderBy('is_popular', 'desc')
                      ->orderBy('sort_order')
                      ->orderBy('name');
                }])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return $this->success([
                'partner'    => $partner->only(['id', 'name', 'logo_url', 'description', 'rating', 'total_reviews', 'delivery_fee', 'estimated_delivery_minutes', 'is_open']),
                'categories' => $categories,
            ], 'Menu retrieved successfully');
        } catch (\Exception $e) {
            \Log::error('Menu endpoint error', [
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->error('Failed to load menu: ' . $e->getMessage(), 500);
        }
    }
}
