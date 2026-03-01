<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * GET /api/v1/services
     * Optional filters:
     *  - partner_id
     *  - only_active=1
     */
    public function index(Request $request): JsonResponse
    {
        // Cache key based on query parameters
        $cacheKey = 'services:' . md5(json_encode($request->all()));
        
        // Cache for 5 minutes (300 seconds)
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

        return response()->json($services);
    }

    /**
     * GET /api/v1/services/{service}
     */
    public function show(Service $service): JsonResponse
    {
        // Cache individual service for 10 minutes
        $cached = cache()->remember("service:{$service->id}", 600, function () use ($service) {
            return $service;
        });
        
        return response()->json($cached);
    }
}
