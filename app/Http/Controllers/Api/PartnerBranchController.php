<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnerBranchRequest;
use App\Http\Requests\UpdatePartnerBranchRequest;
use App\Models\Partner;
use App\Models\PartnerBranch;
use Illuminate\Http\JsonResponse;

class PartnerBranchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/partners/{partnerId}/branches",
     *     summary="List branches for a partner",
     *     tags={"Partner Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="partnerId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of branches",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PartnerBranch"))
     *         )
     *     ),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function index(int $partnerId): JsonResponse
    {
        $partner = Partner::findOrFail($partnerId);

        return $this->success($partner->branches()->orderBy('id', 'desc')->get());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/partners/{partnerId}/branches",
     *     summary="Create a branch for a partner",
     *     tags={"Partner Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="partnerId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"},
     *         @OA\Property(property="name", type="string", example="Makati Branch"),
     *         @OA\Property(property="address", type="string"),
     *         @OA\Property(property="latitude", type="number", format="float"),
     *         @OA\Property(property="longitude", type="number", format="float")
     *     )),
     *     @OA\Response(response=201, description="Branch created",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/PartnerBranch"))
     *     ),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function store(StorePartnerBranchRequest $request, int $partnerId): JsonResponse
    {
        $partner = Partner::findOrFail($partnerId);
        $branch  = $partner->branches()->create($request->validated());

        return $this->created($branch, 'Branch created successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/branches/{id}",
     *     summary="Update a branch",
     *     tags={"Partner Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="address", type="string")
     *     )),
     *     @OA\Response(response=200, description="Branch updated",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/PartnerBranch"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function update(UpdatePartnerBranchRequest $request, PartnerBranch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return $this->success($branch, 'Branch updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/branches/{id}",
     *     summary="Delete a branch",
     *     tags={"Partner Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Branch deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branch deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse"),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function destroy(PartnerBranch $branch): JsonResponse
    {
        $branch->delete();

        return $this->message('Branch deleted successfully');
    }
}
