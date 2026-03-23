<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/services",
     *     summary="List all services (public)",
     *     tags={"Services"},
     *     @OA\Parameter(name="partner_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="only_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Paginated services",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Service")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'services:' . md5(json_encode($request->all()));

        $services = cache()->remember($cacheKey, 300, function () use ($request) {
            $query = Service::query();

            if ($request->filled('partner_id')) {
                $query->where('partner_id', $request->integer('partner_id'));
            }

            if ($request->boolean('only_active')) {
                $query->where('is_active', true);
            }

            return $query->orderBy('id', 'desc')->paginate(20);
        });

        return $this->success($services);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/services/{id}",
     *     summary="Get service by ID (public)",
     *     tags={"Services"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Service details",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Service"))
     *     ),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function show(Service $service): JsonResponse
    {
        $cached = cache()->remember("service:{$service->id}", 600, fn () => $service);

        return $this->success($cached);
    }
}
