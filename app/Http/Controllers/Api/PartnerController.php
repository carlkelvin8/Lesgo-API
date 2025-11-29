<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Partner",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", nullable=true),
 *     @OA\Property(property="name", type="string", example="LeSGo Logistics"),
 *     @OA\Property(property="legal_name", type="string", nullable=true),
 *     @OA\Property(property="slug", type="string", example="lesgo-logistics"),
 *     @OA\Property(property="business_type", type="string", example="logistics"),
 *     @OA\Property(property="status", type="string", example="pending")
 * )
 */
class PartnerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/partners",
     *     summary="List partners",
     *     tags={"Partners"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", example="approved")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of partners",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Partner"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Partner::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderBy('id', 'desc')->paginate(20));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/partners",
     *     summary="Create partner",
     *     tags={"Partners"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="user_id", type="integer", nullable=true),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="legal_name", type="string"),
     *             @OA\Property(property="slug", type="string"),
     *             @OA\Property(property="business_type", type="string"),
     *             @OA\Property(property="tax_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Partner created")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'       => ['nullable', 'integer'],
            'name'          => ['required', 'string', 'max:255'],
            'legal_name'    => ['nullable', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', 'unique:partners,slug'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'tax_id'        => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:100'],
        ]);

        $partner = Partner::create($data);

        return response()->json($partner, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/partners/{id}",
     *     summary="Get partner by ID",
     *     tags={"Partners"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Partner details")
     * )
     */
    public function show(Partner $partner)
    {
        $partner->load('branches');

        return response()->json($partner);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/partners/{id}",
     *     summary="Update partner",
     *     tags={"Partners"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="business_type", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Partner updated")
     * )
     */
    public function update(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'legal_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'        => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $partner->update($data);

        return response()->json($partner);
    }
}
