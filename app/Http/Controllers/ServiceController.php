<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Service",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="partner_id", type="integer", nullable=true, example=2),
 *     @OA\Property(property="code", type="string", example="RIDE_MOTO"),
 *     @OA\Property(property="name", type="string", example="Motorcycle Ride"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="base_fare", type="number", format="float", example=30.00),
 *     @OA\Property(property="per_km_rate", type="number", format="float", example=10.00),
 *     @OA\Property(property="per_minute_rate", type="number", format="float", example=2.00),
 *     @OA\Property(property="minimum_fare", type="number", format="float", example=50.00),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class ServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/services",
     *     summary="List services",
     *     tags={"Services"},
     *     @OA\Parameter(
     *         name="partner_id",
     *         in="query",
     *         description="Filter by partner ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="only_active",
     *         in="query",
     *         description="If 1, return only active services",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0,1})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of services",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Service")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Service::query();

        if ($partnerId = $request->query('partner_id')) {
            $query->where('partner_id', $partnerId);
        }

        if ($request->boolean('only_active', true)) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/services/{id}",
     *     summary="Get service by ID",
     *     tags={"Services"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Service ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service details",
     *         @OA\JsonContent(ref="#/components/schemas/Service")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Service $service)
    {
        return response()->json($service);
    }
}
