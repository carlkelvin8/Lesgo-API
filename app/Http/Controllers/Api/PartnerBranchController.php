<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerBranch;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="PartnerBranch",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="partner_id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="phone_number", type="string"),
 *     @OA\Property(property="address_line1", type="string"),
 *     @OA\Property(property="city", type="string"),
 *     @OA\Property(property="is_primary", type="boolean")
 * )
 */
class PartnerBranchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/partners/{partner_id}/branches",
     *     summary="List partner branches",
     *     tags={"Partner Branches"},
     *     @OA\Parameter(
     *         name="partner_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="List of branches")
     * )
     */
    public function index(int $partnerId)
    {
        $partner = Partner::findOrFail($partnerId);

        return response()->json($partner->branches()->orderBy('id', 'desc')->get());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/partners/{partner_id}/branches",
     *     summary="Create partner branch",
     *     tags={"Partner Branches"},
     *     @OA\Parameter(
     *         name="partner_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             required={"name","address_line1"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="phone_number", type="string"),
     *             @OA\Property(property="address_line1", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="is_primary", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Branch created")
     * )
     */
    public function store(Request $request, int $partnerId)
    {
        $partner = Partner::findOrFail($partnerId);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'phone_number'  => ['nullable', 'string', 'max:100'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'region'        => ['nullable', 'string', 'max:100'],
            'country'       => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'latitude'      => ['nullable', 'numeric'],
            'longitude'     => ['nullable', 'numeric'],
            'is_primary'    => ['nullable', 'boolean'],
            'opening_hours' => ['nullable', 'array'],
        ]);

        $branch = $partner->branches()->create($data);

        return response()->json($branch, 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/branches/{id}",
     *     summary="Update a branch",
     *     tags={"Partner Branches"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Branch updated")
     * )
     */
    public function update(Request $request, PartnerBranch $branch)
    {
        $data = $request->validate([
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'address_line1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'region'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'country'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'latitude'      => ['sometimes', 'nullable', 'numeric'],
            'longitude'     => ['sometimes', 'nullable', 'numeric'],
            'is_primary'    => ['sometimes', 'nullable', 'boolean'],
            'opening_hours' => ['sometimes', 'nullable', 'array'],
        ]);

        $branch->update($data);

        return response()->json($branch);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/branches/{id}",
     *     summary="Delete branch",
     *     tags={"Partner Branches"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Branch deleted")
     * )
     */
    public function destroy(PartnerBranch $branch)
    {
        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }
}
