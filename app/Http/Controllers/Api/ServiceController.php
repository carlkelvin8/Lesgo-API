<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
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

    public function show(Service $service): JsonResponse
    {
        $cached = cache()->remember("service:{$service->id}", 600, fn () => $service);

        return $this->success($cached);
    }
}
