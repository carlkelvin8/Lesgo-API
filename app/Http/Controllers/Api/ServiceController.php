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
     *     summary="List all available services (public)",
     *     tags={"Services"},
     *     @OA\Parameter(name="partner_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="only_active", in="query", required=false, @OA\Schema(type="boolean", example=true)),
     *     @OA\Response(response=200, description="List of services",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="LESGO"),
     *                 @OA\Property(property="name", type="string", example="LesGo"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="icon_url", type="string", nullable=true),
     *                 @OA\Property(property="image_url", type="string", nullable=true),
     *                 @OA\Property(property="category", type="string", nullable=true),
     *                 @OA\Property(property="features", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="base_fare", type="number", example=40.00),
     *                 @OA\Property(property="per_km_rate", type="number", example=9.50),
     *                 @OA\Property(property="minimum_fare", type="number", example=40.00),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             ))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'services:list:' . md5(json_encode($request->all()));

        $services = cache()->remember($cacheKey, 300, function () use ($request) {
            $query = Service::query();

            // Default: only active services
            if ($request->boolean('only_active', true)) {
                $query->where('is_active', true);
            }

            if ($request->filled('partner_id')) {
                $query->where('partner_id', $request->integer('partner_id'));
            }

            if ($request->filled('category')) {
                $query->where('category', $request->input('category'));
            }

            return $query->orderBy('sort_order')->orderBy('name')->get();
        });

        return $this->success($services);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/services/{id}",
     *     summary="Get service details by ID (public)",
     *     tags={"Services"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Service details"),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function show(Service $service): JsonResponse
    {
        $cached = cache()->remember("service:{$service->id}", 600, fn () => $service);

        return $this->success($cached);
    }
}
